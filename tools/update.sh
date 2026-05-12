#!/bin/bash
# ============================================================
# Unbound Dashboard — Update Script v2.2.0+
#
# Aplica um pacote de update (.tar.gz ou diretório extraído) em
# uma instalação existente. Stack: PHP + FastAPI/DuckDB/Redis.
#
# Uso:
#   sudo bash update.sh /tmp/unbound-dashboard-update-*.tar.gz
#   sudo bash update.sh /caminho/para/diretorio-extraido
#
# Variáveis de ambiente:
#   DRY_RUN=true          Simula sem aplicar mudanças
#   AUTO_RESTART=false    Não reinicia api_service/Apache (default: true)
#   VERBOSE=true          Saída detalhada
#   SKIP_VENV_SYNC=true   Pula `uv sync` mesmo se pyproject.toml mudou
# ============================================================

set -euo pipefail

# ============================================================
# CONFIG
# ============================================================
DASHBOARD_DIR="/var/www/html/unbound-dashboard"
APISERVICE_DIR="$DASHBOARD_DIR/api_service"
ETC_DIR="/etc/unbound-dashboard"
ENV_FILE="$ETC_DIR/api-v1.env"
DUCKDB_PATH="/var/lib/unbound-dashboard/unbound_dash.duckdb"
BACKUP_DIR="/var/backups/unbound-dashboard"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
UPDATE_PACKAGE="${1:-}"
DRY_RUN="${DRY_RUN:-false}"
VERBOSE="${VERBOSE:-false}"
AUTO_RESTART="${AUTO_RESTART:-true}"
SKIP_VENV_SYNC="${SKIP_VENV_SYNC:-false}"

EXTRACTED_DIR=""

# ============================================================
# LOGGING
# ============================================================
log()   { echo "[OK] $1"; }
info()  { echo "[..] $1"; }
warn()  { echo "[!!] $1"; }
error() { echo "[XX] $1" >&2; }
debug() { [ "$VERBOSE" = "true" ] && echo "[??] $1" || true; }

cleanup_extracted() {
    if [ -n "$EXTRACTED_DIR" ] && [[ "$EXTRACTED_DIR" == /tmp/unbound-dashboard-update-* ]]; then
        rm -rf "$EXTRACTED_DIR"
    fi
}
trap cleanup_extracted EXIT

# ============================================================
# VALIDAÇÃO INICIAL
# ============================================================
validate_environment() {
    info "Validando ambiente..."

    if [ "$EUID" -ne 0 ]; then
        error "Execute como root (sudo bash update.sh ...)"
        exit 1
    fi

    if [ ! -d "$DASHBOARD_DIR" ]; then
        error "Dashboard não instalado em $DASHBOARD_DIR — use install.sh primeiro"
        exit 1
    fi

    if [ -z "$UPDATE_PACKAGE" ]; then
        error "Pacote de update obrigatório"
        echo ""
        echo "Uso:"
        echo "  sudo bash update.sh /tmp/unbound-dashboard-update-*.tar.gz"
        echo "  sudo bash update.sh /caminho/para/diretorio-extraido"
        echo ""
        echo "Variáveis opcionais:"
        echo "  DRY_RUN=true              Simula sem aplicar mudanças"
        echo "  AUTO_RESTART=false        Não reinicia serviços ao final"
        echo "  SKIP_VENV_SYNC=true       Pula uv sync mesmo se pyproject mudou"
        echo "  VERBOSE=true              Saída detalhada"
        exit 1
    fi

    if [ ! -f "$UPDATE_PACKAGE" ] && [ ! -d "$UPDATE_PACKAGE" ]; then
        error "Pacote não encontrado: $UPDATE_PACKAGE"
        exit 1
    fi

    php -r 'exit(0);' >/dev/null 2>&1 || { error "PHP indisponível"; exit 1; }

    log "Ambiente OK"
}

# ============================================================
# EXTRAÇÃO
# ============================================================
extract_update() {
    if [ -d "$UPDATE_PACKAGE" ]; then
        EXTRACTED_DIR="$UPDATE_PACKAGE"
        log "Usando diretório: $EXTRACTED_DIR"
        return 0
    fi

    info "Extraindo pacote..."
    EXTRACTED_DIR="/tmp/unbound-dashboard-update-$$"
    mkdir -p "$EXTRACTED_DIR"
    tar xzf "$UPDATE_PACKAGE" -C "$EXTRACTED_DIR" || { error "Falha ao extrair $UPDATE_PACKAGE"; exit 1; }

    # Se o tar tem um único diretório raiz, desce um nível
    local inner
    inner=$(find "$EXTRACTED_DIR" -mindepth 1 -maxdepth 1 -type d | head -1)
    if [ -n "$inner" ] && [ "$(find "$EXTRACTED_DIR" -mindepth 1 -maxdepth 1 | wc -l)" = "1" ]; then
        EXTRACTED_DIR="$inner"
    fi

    log "Pacote extraído em: $EXTRACTED_DIR"
}

# ============================================================
# VALIDAÇÃO DOS ARQUIVOS DO PACOTE
# ============================================================
validate_package_files() {
    info "Validando sintaxe dos arquivos do pacote..."

    local fail=0
    while IFS= read -r php_file; do
        if ! php -l "$php_file" >/dev/null 2>&1; then
            error "Sintaxe PHP inválida: $php_file"
            fail=1
        fi
    done < <(find "$EXTRACTED_DIR" -name "*.php" -type f)

    while IFS= read -r sh_file; do
        if ! bash -n "$sh_file" 2>/dev/null; then
            error "Sintaxe bash inválida: $sh_file"
            fail=1
        fi
    done < <(find "$EXTRACTED_DIR" -name "*.sh" -type f)

    [ "$fail" -eq 0 ] || { error "Validação falhou — abortando antes de aplicar update"; exit 1; }
    log "Sintaxe validada"
}

# ============================================================
# BACKUP PRÉ-UPDATE
# ============================================================
create_backup() {
    info "Criando backup pré-update..."
    [ "$DRY_RUN" = "true" ] && { info "[DRY-RUN] Pulando backup"; return 0; }

    mkdir -p "$BACKUP_DIR"

    # Código (exclui lixo voláteis e .venv pra ficar pequeno e rápido)
    local code_backup="$BACKUP_DIR/dashboard-$TIMESTAMP.tar.gz"
    tar czf "$code_backup" \
        --exclude='api_service/.venv' \
        --exclude='api_service/__pycache__' \
        --exclude='**/__pycache__' \
        --exclude='*.pyc' \
        --exclude='data/tmp/*' \
        --exclude='src/data/tmp/*' \
        -C "$(dirname "$DASHBOARD_DIR")" \
        "$(basename "$DASHBOARD_DIR")" 2>/dev/null
    log "Código: $code_backup ($(du -h "$code_backup" | cut -f1))"

    # DuckDB
    if [ -f "$DUCKDB_PATH" ]; then
        local db_backup="$BACKUP_DIR/duckdb-$TIMESTAMP.duckdb"
        cp -a "$DUCKDB_PATH" "$db_backup"
        log "DuckDB: $db_backup ($(du -h "$db_backup" | cut -f1))"
    else
        warn "DuckDB não encontrado em $DUCKDB_PATH — backup do banco pulado"
    fi

    # Env file (preserva JWT_SECRET — crítico)
    if [ -f "$ENV_FILE" ]; then
        cp -a "$ENV_FILE" "$BACKUP_DIR/api-v1.env-$TIMESTAMP"
        log "Env file: $BACKUP_DIR/api-v1.env-$TIMESTAMP"
    fi
}

# ============================================================
# APLICAR DASHBOARD PHP (frontend)
# ============================================================
apply_dashboard() {
    local src="$EXTRACTED_DIR/dashboard"
    [ -d "$src" ] || { warn "dashboard/ ausente no pacote — pulando frontend"; return 0; }

    info "Atualizando frontend PHP..."
    if [ "$DRY_RUN" = "true" ]; then
        info "[DRY-RUN] rsync $src/ -> $DASHBOARD_DIR/"
        return 0
    fi

    # Preserva: data/, src/data/ (volátil), Database.php (stub local pode ter divergência),
    # api_service/ (atualizado em outra etapa).
    rsync -a \
        --exclude='data/' \
        --exclude='src/data/' \
        --exclude='api_service/' \
        --exclude='Database.php' \
        "$src/" "$DASHBOARD_DIR/"

    log "Frontend PHP atualizado"
}

# ============================================================
# APLICAR API_SERVICE (FastAPI/DuckDB)
# ============================================================
apply_apiservice() {
    local src="$EXTRACTED_DIR/api_service"
    [ -d "$src" ] || { warn "api_service/ ausente no pacote — pulando backend"; return 0; }

    info "Atualizando api_service..."
    if [ "$DRY_RUN" = "true" ]; then
        info "[DRY-RUN] rsync $src/ -> $APISERVICE_DIR/ (preservando .venv)"
        return 0
    fi

    # Detecta se pyproject.toml mudou (pra decidir se precisa uv sync)
    local need_uv_sync=false
    if [ -f "$src/pyproject.toml" ] && [ -f "$APISERVICE_DIR/pyproject.toml" ]; then
        if ! cmp -s "$src/pyproject.toml" "$APISERVICE_DIR/pyproject.toml"; then
            need_uv_sync=true
            debug "pyproject.toml mudou — uv sync será necessário"
        fi
    fi
    if [ -f "$src/uv.lock" ] && [ -f "$APISERVICE_DIR/uv.lock" ]; then
        if ! cmp -s "$src/uv.lock" "$APISERVICE_DIR/uv.lock"; then
            need_uv_sync=true
            debug "uv.lock mudou — uv sync será necessário"
        fi
    fi

    rsync -a \
        --exclude='.venv' \
        --exclude='__pycache__' \
        --exclude='*.pyc' \
        --delete-excluded \
        "$src/" "$APISERVICE_DIR/"

    log "api_service atualizado"

    if [ "$need_uv_sync" = "true" ] && [ "$SKIP_VENV_SYNC" != "true" ]; then
        info "pyproject/uv.lock mudaram — rodando uv sync..."
        local uv_bin
        uv_bin="$(command -v uv || echo /usr/local/bin/uv)"
        if [ ! -x "$uv_bin" ]; then
            warn "uv não disponível — pulei uv sync; instale com: curl -fsSL https://astral.sh/uv/install.sh | sh"
        else
            (cd "$APISERVICE_DIR" && "$uv_bin" sync --no-dev --quiet) || warn "uv sync falhou — venv pode estar inconsistente"
            log "venv sincronizado"
        fi
    elif [ "$SKIP_VENV_SYNC" = "true" ]; then
        debug "SKIP_VENV_SYNC=true — pulando uv sync"
    fi
}

# ============================================================
# APLICAR ARQUIVOS DE SISTEMA (sudoers, systemd, apache, bin, cron, etc)
# ============================================================
apply_system() {
    local sys="$EXTRACTED_DIR/system"
    [ -d "$sys" ] || { warn "system/ ausente no pacote — pulando configs"; return 0; }

    info "Atualizando configurações do sistema..."

    # --- Sudoers
    if [ -f "$sys/sudoers/unbound-dashboard" ]; then
        if [ "$DRY_RUN" = "true" ]; then
            info "[DRY-RUN] /etc/sudoers.d/unbound-dashboard"
        else
            cp "$sys/sudoers/unbound-dashboard" /etc/sudoers.d/unbound-dashboard
            chmod 440 /etc/sudoers.d/unbound-dashboard
            visudo -c -f /etc/sudoers.d/unbound-dashboard >/dev/null || error "Sudoers inválido após update!"
            log "Sudoers atualizado"
        fi
    fi

    # --- Systemd unit do api_service
    if [ -f "$sys/systemd/unbound-dashboard-api.service" ]; then
        if [ "$DRY_RUN" = "true" ]; then
            info "[DRY-RUN] /etc/systemd/system/unbound-dashboard-api.service"
        else
            cp "$sys/systemd/unbound-dashboard-api.service" /etc/systemd/system/
            systemctl daemon-reload
            log "Systemd unit atualizada"
        fi
    fi

    # --- Apache conf-available
    if [ -f "$sys/apache/unbound-dashboard-api.conf" ]; then
        if [ "$DRY_RUN" = "true" ]; then
            info "[DRY-RUN] /etc/apache2/conf-available/unbound-dashboard-api.conf"
        else
            cp "$sys/apache/unbound-dashboard-api.conf" /etc/apache2/conf-available/
            a2enconf unbound-dashboard-api >/dev/null 2>&1 || true
            log "Apache conf atualizado"
        fi
    fi

    # --- PHP-FPM (idempotente) — corrige instalações pré-2.2.10 que usavam
    # mod_php. Sem isso, `.php` sai cru no browser.
    if [ "$DRY_RUN" != "true" ]; then
        local php_fpm_svc
        php_fpm_svc=$(systemctl list-unit-files --type=service --no-legend 2>/dev/null \
            | awk '{print $1}' | grep -E '^php[0-9.]+-fpm\.service$' | sort -V | tail -1)
        if [ -z "$php_fpm_svc" ]; then
            info "php-fpm não instalado — instalando agora (necessário desde v2.2.10)"
            DEBIAN_FRONTEND=noninteractive apt-get install -y -qq php-fpm \
                || warn "Falha ao instalar php-fpm — .php pode não ser interpretado"
            php_fpm_svc=$(systemctl list-unit-files --type=service --no-legend 2>/dev/null \
                | awk '{print $1}' | grep -E '^php[0-9.]+-fpm\.service$' | sort -V | tail -1)
        fi
        if [ -n "$php_fpm_svc" ]; then
            local php_fpm_conf="${php_fpm_svc%.service}"
            local php_fpm_conf_file="/etc/apache2/conf-available/${php_fpm_conf}.conf"
            local php_fpm_version="${php_fpm_conf#php}"
            php_fpm_version="${php_fpm_version%-fpm}"
            local php_fpm_socket="/run/php/php${php_fpm_version}-fpm.sock"
            a2enmod proxy_fcgi setenvif proxy proxy_http >/dev/null 2>&1 || true
            # Desabilita mod_php legado se presente
            local legacy_mod_php
            legacy_mod_php=$(a2query -m 2>/dev/null | awk '{print $1}' | grep -E '^php[0-9.]+$' || true)
            if [ -n "$legacy_mod_php" ]; then
                for m in $legacy_mod_php; do
                    a2dismod "$m" >/dev/null 2>&1 || true
                    info "mod_php '$m' desabilitado (substituído por PHP-FPM)"
                done
            fi
            # Debian 13/PHP 8.4: o pacote php-fpm não cria conf-available/phpX.Y-fpm.conf.
            # Gera manualmente com handler proxy:unix:.../sock.
            if [ ! -f "$php_fpm_conf_file" ]; then
                info "Gerando $php_fpm_conf_file (não vem no pacote php-fpm do Debian 13)"
                cat > "$php_fpm_conf_file" <<APACHE_PHP_FPM
# Gerado pelo Unbound Dashboard update.sh — handler de .php via PHP-FPM ${php_fpm_version}
<FilesMatch ".+\.ph(ar|p|tml)\$">
    SetHandler "proxy:unix:${php_fpm_socket}|fcgi://localhost"
</FilesMatch>
<FilesMatch ".+\.phps\$">
    SetHandler application/x-httpd-php-source
    Require all denied
</FilesMatch>
<FilesMatch "^\.ph(ar|p|ps|tml)\$">
    Require all denied
</FilesMatch>
DirectoryIndex index.php
APACHE_PHP_FPM
            fi
            a2enconf "$php_fpm_conf" >/dev/null 2>&1 || warn "a2enconf $php_fpm_conf falhou"
            systemctl enable --now "$php_fpm_svc" >/dev/null 2>&1 || warn "Falha ao habilitar $php_fpm_svc"
            log "PHP-FPM verificado: $php_fpm_conf ativo"
        else
            warn "php-fpm ainda ausente após install — Apache pode servir .php cru"
        fi
    fi

    # --- Env example (NÃO sobrescreve api-v1.env real, só o exemplo)
    if [ -f "$sys/etc/api-v1.env.example" ]; then
        if [ "$DRY_RUN" = "true" ]; then
            info "[DRY-RUN] $ETC_DIR/api-v1.env.example"
        else
            mkdir -p "$ETC_DIR"
            cp "$sys/etc/api-v1.env.example" "$ETC_DIR/api-v1.env.example"
            chown root:www-data "$ETC_DIR/api-v1.env.example"
            chmod 640 "$ETC_DIR/api-v1.env.example"
            debug "Template env atualizado"
        fi
    fi

    # --- Scripts em /usr/local/bin
    if [ -d "$sys/bin" ]; then
        for sh in "$sys/bin"/*.sh; do
            [ -f "$sh" ] || continue
            local name; name=$(basename "$sh")
            if [ "$DRY_RUN" = "true" ]; then
                info "[DRY-RUN] /usr/local/bin/$name"
            else
                cp "$sh" "/usr/local/bin/$name"
                chmod +x "/usr/local/bin/$name"
                debug "/usr/local/bin/$name atualizado"
            fi
        done
        log "Scripts /usr/local/bin/ atualizados"
    fi

    # --- Crontabs
    if [ -f "$sys/cron/unbound-dashboard-crons" ]; then
        if [ "$DRY_RUN" = "true" ]; then
            info "[DRY-RUN] crontab"
        else
            local tmp_cron="/tmp/unbound-dashboard-cron-$$"
            crontab -l 2>/dev/null | grep -v 'UNBOUND-DASHBOARD' > "$tmp_cron" || true
            cat "$sys/cron/unbound-dashboard-crons" >> "$tmp_cron"
            sed -i -e '$a\' "$tmp_cron"
            crontab "$tmp_cron"
            rm -f "$tmp_cron"
            log "Crontabs atualizadas"
        fi
    fi
}

# ============================================================
# PERMISSÕES
# ============================================================
reset_permissions() {
    info "Resetando permissões..."
    [ "$DRY_RUN" = "true" ] && { info "[DRY-RUN] Pulando chown/chmod"; return 0; }

    chown -R www-data:www-data "$DASHBOARD_DIR"
    find "$DASHBOARD_DIR" -name "*.php" -type f -exec chmod 644 {} \;
    find "$DASHBOARD_DIR" -name "*.sh" -type f -exec chmod 755 {} \;
    [ -d "$DASHBOARD_DIR/data" ] && chmod 750 "$DASHBOARD_DIR/data"
    [ -d "$DASHBOARD_DIR/data/tmp" ] && chmod 770 "$DASHBOARD_DIR/data/tmp"
    [ -d "$DASHBOARD_DIR/src/data" ] && chmod 750 "$DASHBOARD_DIR/src/data"
    [ -d "$DASHBOARD_DIR/src/data/tmp" ] && chmod 770 "$DASHBOARD_DIR/src/data/tmp"

    log "Permissões aplicadas"
}

# ============================================================
# RESTART E SMOKE-TEST
# ============================================================
restart_and_smoke() {
    [ "$DRY_RUN" = "true" ] && { info "[DRY-RUN] Pulando restart"; return 0; }

    if [ "$AUTO_RESTART" != "true" ]; then
        warn "AUTO_RESTART=false — não reinicio serviços. Faça manualmente:"
        echo "  sudo systemctl restart unbound-dashboard-api"
        echo "  sudo systemctl reload apache2"
        return 0
    fi

    info "Recarregando Apache..."
    systemctl reload apache2 2>/dev/null || systemctl restart apache2 || warn "Apache reload/restart falhou"

    info "Reiniciando api_service..."
    systemctl restart unbound-dashboard-api
    sleep 3

    if systemctl is-active --quiet unbound-dashboard-api; then
        log "api_service ativo"
    else
        error "api_service não subiu — veja logs:"
        journalctl -u unbound-dashboard-api -n 30 --no-pager
        return 1
    fi

    info "Smoke /api/v1/healthz..."
    if curl -sf http://127.0.0.1:8001/api/v1/healthz >/dev/null; then
        log "/api/v1/healthz OK"
    else
        warn "/api/v1/healthz não respondeu — verifique manualmente"
    fi
}

# ============================================================
# RELATÓRIO
# ============================================================
print_report() {
    local end_time elapsed
    end_time=$(date +%s)
    elapsed=$((end_time - START_TIME))

    local final_version="?"
    [ -f "$DASHBOARD_DIR/VERSION" ] && final_version=$(tr -d '[:space:]' < "$DASHBOARD_DIR/VERSION")

    echo ""
    echo "╔════════════════════════════════════════════════════╗"
    echo "║       Update concluído                             ║"
    echo "╚════════════════════════════════════════════════════╝"
    echo ""
    echo "Modo:       $([ "$DRY_RUN" = "true" ] && echo "DRY-RUN (nada foi aplicado)" || echo "Aplicado")"
    echo "Duração:    ${elapsed}s"
    echo "Versão:     $final_version"
    echo "Backups:    $BACKUP_DIR/{dashboard,duckdb,api-v1.env}-$TIMESTAMP*"
    echo ""
    echo "Rollback (se necessário):"
    echo "  sudo systemctl stop unbound-dashboard-api"
    echo "  sudo tar xzf $BACKUP_DIR/dashboard-$TIMESTAMP.tar.gz -C /"
    echo "  sudo cp -a $BACKUP_DIR/duckdb-$TIMESTAMP.duckdb $DUCKDB_PATH"
    echo "  sudo cp -a $BACKUP_DIR/api-v1.env-$TIMESTAMP $ENV_FILE"
    echo "  sudo systemctl start unbound-dashboard-api"
    echo ""
}

# ============================================================
# MAIN
# ============================================================
main() {
    START_TIME=$(date +%s)

    echo "╔════════════════════════════════════════════════════╗"
    echo "║   Unbound Dashboard — Update                       ║"
    echo "╚════════════════════════════════════════════════════╝"
    echo ""
    [ "$DRY_RUN" = "true" ] && warn "Modo DRY-RUN — nenhuma mudança será aplicada"
    echo ""

    validate_environment
    extract_update
    validate_package_files
    create_backup
    apply_dashboard
    apply_apiservice
    apply_system
    reset_permissions
    restart_and_smoke
    print_report
}

main
