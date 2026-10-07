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
# Quantos conjuntos de backup (código + DuckDB + env) manter, contando o
# deste update. Cada snapshot do DuckDB pode ter GBs — sem limite o disco enche.
BACKUP_KEEP="${BACKUP_KEEP:-3}"

# uv: cache e Pythons baixados em dirs do root fora de /root. O self-update
# roda no sandbox da API (ProtectHome=yes): /root/.cache fica read-only e o
# `uv sync` falhava; um Python standalone em /root/.local ficaria invisível
# para a API. Os dois dirs estão no ReadWritePaths do unit.
export UV_CACHE_DIR=/var/cache/unbound-dashboard/uv
export UV_PYTHON_INSTALL_DIR=/usr/local/lib/unbound-dashboard/python
install -d -o root -g root -m 755 "$UV_CACHE_DIR" "$UV_PYTHON_INSTALL_DIR" 2>/dev/null || true
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

# Instala o cron em /etc/cron.d (como www-data) e remove do crontab do root as
# entradas antigas do dashboard: linhas com a tag UNBOUND-DASHBOARD e as linhas
# de cabeçalho que versões anteriores duplicavam a cada instalação.
install_dashboard_cron() {
    local src="$1"
    install -m 0644 -o root -g root "$src" /etc/cron.d/unbound-dashboard || return 1
    local cur new
    cur=$(crontab -l 2>/dev/null || true)
    [ -n "$cur" ] || return 0
    new=$(printf '%s\n' "$cur" | grep -v 'UNBOUND-DASHBOARD' | grep -vxF \
        -e '# Unbound Dashboard — Crontabs' \
        -e '# Instalar com: crontab -l | cat - system/cron/unbound-dashboard-crons | crontab -' \
        -e '#' \
        -e '# Os crons de agregação de estatísticas e monitoramento de alertas foram' \
        -e '# cutovered para os workers Python do api_service em 2026-04-29:' \
        -e '#   - stats_aggregator.py  (substitui aggregate_stats.php)' \
        -e '#   - alert_checker.py     (substitui cron_alerts.php)' \
        -e '#   - log_watcher.py       (substitui log_ingester.php)' \
        -e '# Eles rodam dentro do unbound-dashboard-api.service.' \
        -e '# Sincronização de blacklist principal (a cada hora)' \
        -e '# Spawned por api/service_control.php com JWT via env, mas mantido também aqui' \
        -e '# como fallback caso o admin queira agendar sincronização periódica automática.' \
        -e '# Sincronização de lista judicial ANATEL (diário às 04:30)' \
        -e '# Agregação de estatísticas (a cada minuto)' \
        -e '# Monitoramento de alertas (a cada minuto)' \
        | cat -s || true)
    if [ -z "$(printf '%s' "$new" | tr -d '[:space:]')" ]; then
        crontab -r 2>/dev/null || true
    else
        printf '%s\n' "$new" | crontab -
    fi
}

cleanup_extracted() {
    if [ -n "$EXTRACTED_DIR" ] && [[ "$EXTRACTED_DIR" == /tmp/unbound-dashboard-update-* ]]; then
        rm -rf "$EXTRACTED_DIR"
    fi
}

# Estado para o handler de saída: create_backup para a API (snapshot
# consistente) e as etapas apply_* alteram o sistema. Se o script abortar no
# meio (set -e), sem isto a API ficava parada e o código pela metade.
API_STOPPED_BY_UPDATE="false"
APPLY_STARTED="false"
UPDATE_DONE="false"
ROLLBACK_RAN="false"

on_exit() {
    local rc=$?
    cleanup_extracted
    if [ "$rc" -ne 0 ] && [ "$UPDATE_DONE" != "true" ] && [ "$ROLLBACK_RAN" != "true" ] \
       && [ "$DRY_RUN" != "true" ]; then
        if [ "$APPLY_STARTED" = "true" ]; then
            error "Update abortado no meio (exit $rc) — restaurando o backup"
            rollback_from_backup
        elif [ "$API_STOPPED_BY_UPDATE" = "true" ]; then
            error "Update abortado antes de aplicar (exit $rc) — religando a API"
            systemctl start unbound-dashboard-api || true
            echo "ROLLBACK CONCLUÍDO — nada foi aplicado"
        fi
    fi
}
trap on_exit EXIT

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
# Remove conjuntos antigos (dashboard-/duckdb-/api-v1.env-<TS>), deixando
# BACKUP_KEEP-1 para que, com o deste update, fiquem BACKUP_KEEP. Roda ANTES
# do backup novo para liberar espaço. Arquivos com outros nomes (dumps
# manuais, legado MariaDB) não são tocados.
prune_old_backups() {
    local keep=$((BACKUP_KEEP - 1))
    [ "$keep" -lt 1 ] && keep=1
    local old_ts
    old_ts=$(find "$BACKUP_DIR" -maxdepth 1 -type f \
                -regextype posix-extended \
                -regex '.*/(dashboard|duckdb|api-v1\.env)-[0-9]{8}_[0-9]{6}.*' -printf '%f\n' \
             | grep -oE '[0-9]{8}_[0-9]{6}' | sort -u | head -n -"$keep" || true)
    local ts
    for ts in $old_ts; do
        rm -f -- "$BACKUP_DIR/dashboard-$ts.tar.gz" \
                 "$BACKUP_DIR/duckdb-$ts.duckdb" "$BACKUP_DIR/duckdb-$ts.duckdb.wal" \
                 "$BACKUP_DIR/api-v1.env-$ts"
        info "Backup antigo removido: *-$ts (BACKUP_KEEP=$BACKUP_KEEP)"
    done
}

# Aborta antes de começar se não couber o snapshot do DuckDB + 512MB de folga.
# Disco cheio no meio do update deixa o DuckDB com WAL que não reproduz.
check_backup_space() {
    [ -f "$DUCKDB_PATH" ] || return 0
    local need avail
    need=$(( $(stat -c %s "$DUCKDB_PATH") + 512 * 1024 * 1024 ))
    avail=$(df -B1 --output=avail "$BACKUP_DIR" | tail -1 | tr -d ' ')
    if [ "$avail" -lt "$need" ]; then
        error "Espaço insuficiente em $BACKUP_DIR: livre $((avail / 1048576))MB, necessário $((need / 1048576))MB"
        error "Libere espaço (ex.: /var/log/unbound/unbound.log, backups antigos) e rode de novo"
        exit 1
    fi
}

create_backup() {
    info "Criando backup pré-update..."
    [ "$DRY_RUN" = "true" ] && { info "[DRY-RUN] Pulando backup"; return 0; }

    mkdir -p "$BACKUP_DIR"
    prune_old_backups
    check_backup_space

    # Garante dir de updates pro pipeline UI (idempotente — install.sh já cria,
    # mas updates aplicados manualmente também precisam dele depois)
    if [ ! -d /var/lib/unbound-dashboard/updates ]; then
        mkdir -p /var/lib/unbound-dashboard/updates
        chown www-data:www-data /var/lib/unbound-dashboard/updates
        chmod 750 /var/lib/unbound-dashboard/updates
    fi

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
        # Snapshot consistente: com a API rodando o arquivo muda durante o cp
        # e as escritas recentes estão no .wal. Parar a API faz o DuckDB
        # gravar o WAL no arquivo (CHECKPOINT no shutdown). restart_and_smoke
        # sobe de novo no fim.
        if [ "$AUTO_RESTART" = "true" ]; then
            info "Parando unbound-dashboard-api para snapshot consistente do DuckDB..."
            API_STOPPED_BY_UPDATE="true"
            systemctl stop unbound-dashboard-api 2>/dev/null || true
        else
            warn "AUTO_RESTART=false — api_service segue rodando; snapshot do DuckDB pode ficar inconsistente"
        fi
        cp -a "$DUCKDB_PATH" "$db_backup"
        if [ -f "${DUCKDB_PATH}.wal" ]; then
            cp -a "${DUCKDB_PATH}.wal" "${db_backup}.wal"
        fi
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

    # IMPORTANTE: --delete-excluded NÃO está aqui. Adicioná-lo apagaria o
    # .venv do destino — bug que custou várias iterações pra descobrir.
    # As --exclude só impedem cópia do source pro destino (tarball já não
    # inclui .venv); preservar o .venv existente no destino é o que queremos.
    rsync -a \
        --exclude='.venv' \
        --exclude='__pycache__' \
        --exclude='*.pyc' \
        "$src/" "$APISERVICE_DIR/"

    # Limpa __pycache__ pré-existente no destino. Python *normalmente*
    # recompila quando o .py é mais novo que o .pyc, mas houve cenário real
    # (v2.103.0 → v2.103.1, dashboard.redeconexao.net) onde o serviço subiu
    # carregando uma versão antiga do main.py em memória — provavelmente
    # bytecode stale + race com o restart. Limpar é barato (uvicorn recompila
    # em milissegundos no startup) e elimina a classe inteira de bug.
    find "$APISERVICE_DIR/app" -type d -name '__pycache__' -exec rm -rf {} + 2>/dev/null || true

    log "api_service atualizado"

    # uv sync INCONDICIONAL — antes só rodava se pyproject/uv.lock mudasse.
    # Mas há cenários (race condition? cgroup cleanup? bug histórico
    # do --delete-excluded?) onde .venv desaparece DEPOIS do rsync mesmo
    # com pyproject intacto. Rodar uv sync sempre garante venv consistente
    # após cada update. É idempotente: se .venv já está OK, é quase no-op
    # (uv detecta lock match e não baixa nada).
    if [ "$SKIP_VENV_SYNC" = "true" ]; then
        debug "SKIP_VENV_SYNC=true — pulando uv sync"
    else
        info "Sincronizando .venv via uv sync (incondicional)..."
        local uv_bin
        uv_bin="$(command -v uv || echo /usr/local/bin/uv)"
        [ -x "$uv_bin" ] || uv_bin="/root/.local/bin/uv"
        if [ ! -x "$uv_bin" ]; then
            # ANTES era só warn e seguia — mas o resultado é que .venv fica
            # defasado, módulos novos do pyproject não instalam, e o serviço
            # falha no startup com ModuleNotFoundError. Falhar aqui força o
            # operador a instalar uv antes de prosseguir.
            error "uv não disponível — não dá pra sincronizar dependências do api_service"
            error "Instale uv: curl -fsSL https://astral.sh/uv/install.sh | sh"
            error "Ou pule este passo (NÃO RECOMENDADO): SKIP_VENV_SYNC=true bash update.sh ..."
            exit 1
        fi
        # uv sync com falha hard — se quebrar, abortar o update inteiro pra
        # não deixar arquivos novos rodando contra .venv velha.
        if ! (cd "$APISERVICE_DIR" && "$uv_bin" sync --no-dev --quiet); then
            error "uv sync falhou — venv ficou inconsistente. Update abortado."
            error "Rode manualmente: cd $APISERVICE_DIR && $uv_bin sync --no-dev"
            exit 1
        fi
        # Garante owner correto se uv criou .venv do zero (rodando como root)
        chown -R www-data:www-data "$APISERVICE_DIR/.venv" 2>/dev/null || true
        log "venv sincronizado (owner: www-data)"
    fi
}

# ============================================================
# APLICAR ARQUIVOS DE SISTEMA (sudoers, systemd, apache, bin, cron, etc)
# ============================================================
apply_system() {
    local sys="$EXTRACTED_DIR/system"
    [ -d "$sys" ] || { warn "system/ ausente no pacote — pulando configs"; return 0; }

    # Logs de update/restore (scripts root escrevem, API lê via SSE)
    if [ "$DRY_RUN" != "true" ]; then
        install -d -o root -g www-data -m 750 /var/log/unbound-dashboard-update 2>/dev/null \
            || warn "Não foi possível criar /var/log/unbound-dashboard-update"
    fi

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

    # --- Drop-in pro Unbound do sistema (stderr → logfile)
    # Sem isso, em Debian/Ubuntu modernos o LogWatcher fica sem queries
    # porque `unbound -d` joga stderr no journal e ignora logfile:.
    if [ -d "$sys/systemd/unbound.service.d" ]; then
        if [ "$DRY_RUN" = "true" ]; then
            info "[DRY-RUN] /etc/systemd/system/unbound.service.d/ drop-in"
        else
            mkdir -p /etc/systemd/system/unbound.service.d
            cp "$sys/systemd/unbound.service.d/"*.conf /etc/systemd/system/unbound.service.d/
            systemctl daemon-reload
            # Garante que o arquivo de log existe + permissão (systemd vai
            # appendar como root, mas o dir/arquivo precisa ser writable)
            mkdir -p /var/log/unbound
            touch /var/log/unbound/unbound.log
            chown unbound:unbound /var/log/unbound/unbound.log 2>/dev/null || true
            # Restart (não reload) — drop-in muda StandardError, precisa re-exec
            if systemctl is-active --quiet unbound; then
                systemctl restart unbound || warn "Falha ao reiniciar unbound após drop-in"
            fi
            log "Unbound drop-in instalado (stderr→logfile pra LogWatcher)"
        fi
    fi

    # --- Logrotate do log de queries do Unbound
    if [ -f "$sys/logrotate/unbound-dashboard" ]; then
        if [ "$DRY_RUN" = "true" ]; then
            info "[DRY-RUN] /etc/logrotate.d/unbound-dashboard"
        else
            # Self-update pela UI herda o sandbox da API (ProtectSystem=strict):
            # com um unit anterior a esta versão /etc/logrotate.d é read-only.
            # Não aborta o update por isso — o próximo update instala.
            if ! install -m 0644 -o root -g root "$sys/logrotate/unbound-dashboard" /etc/logrotate.d/unbound-dashboard 2>/dev/null; then
                warn "Não foi possível gravar /etc/logrotate.d (read-only no sandbox da API) — rode o update de novo após este"
            elif command -v logrotate >/dev/null 2>&1 && ! logrotate -d /etc/logrotate.d/unbound-dashboard >/dev/null 2>&1; then
                warn "logrotate -d acusou erro em /etc/logrotate.d/unbound-dashboard — revise"
            else
                log "Logrotate do log do Unbound instalado"
            fi
            # copytruncate copia o arquivo antes de truncar: na 1ª rotação de
            # um log que cresceu sem limite (instalações antigas) a cópia pode
            # não caber e encher o disco. Não truncamos sozinhos (o log pode
            # ser a única cópia das queries) — só avisamos.
            local ulog=/var/log/unbound/unbound.log
            if [ -f "$ulog" ]; then
                local ulog_size ulog_avail
                ulog_size=$(stat -c %s "$ulog")
                ulog_avail=$(df -B1 --output=avail "$(dirname "$ulog")" | tail -1 | tr -d ' ')
                if [ "$ulog_size" -gt "$ulog_avail" ]; then
                    warn "$ulog tem $((ulog_size / 1073741824))GB e só há $((ulog_avail / 1073741824))GB livres:"
                    warn "  a 1ª rotação (copytruncate) não cabe e pode encher o disco."
                    warn "  Guarde o log se precisar e rode: truncate -s 0 $ulog"
                fi
            fi
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
    # Nome novo de propósito: o update.sh de versões anteriores procura
    # "unbound-dashboard-crons" e colaria este arquivo (formato /etc/cron.d,
    # com coluna de usuário) no crontab do root. Com o nome novo ele pula.
    if [ -f "$sys/cron/unbound-dashboard.cron" ]; then
        if [ "$DRY_RUN" = "true" ]; then
            info "[DRY-RUN] crontab"
        else
            install -d -o www-data -g www-data -m 750 /var/log/unbound-dashboard
            # Mesmo caso do logrotate: /etc/cron.d pode ser read-only no sandbox.
            if ( install_dashboard_cron "$sys/cron/unbound-dashboard.cron" ) 2>/dev/null; then
                log "Cron instalado em /etc/cron.d/unbound-dashboard (www-data)"
            else
                warn "Não foi possível gravar /etc/cron.d (read-only no sandbox da API) — rode o update de novo após este"
            fi
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

    # Health check resiliente: até 30s pra api_service iniciar e responder
    local max_wait=30
    local waited=0
    local healthy=0
    info "Aguardando api_service ficar saudável (timeout ${max_wait}s)..."
    while [ $waited -lt $max_wait ]; do
        sleep 2
        waited=$((waited + 2))
        if systemctl is-active --quiet unbound-dashboard-api \
           && curl -sf --max-time 3 http://127.0.0.1:8001/api/v1/healthz >/dev/null 2>&1; then
            healthy=1
            log "api_service saudável após ${waited}s"
            break
        fi
    done

    if [ $healthy -eq 0 ]; then
        error "Health check falhou após ${max_wait}s — disparando rollback automático"
        journalctl -u unbound-dashboard-api -n 30 --no-pager || true
        rollback_from_backup
        # rollback_from_backup faz exit; se voltar é porque algo foi bypassed
        return 2
    fi
}

# ============================================================
# ROLLBACK AUTOMÁTICO
# ============================================================
# Restaura backups gravados em create_backup() e reinicia serviços.
# Chamado SOMENTE se restart_and_smoke detectar falha no health check.
#
# Exit codes:
#   2 = rollback executado com sucesso (estado anterior restaurado)
#   3 = ROLLBACK FAILED (estado inconsistente — intervenção manual obrigatória)
rollback_from_backup() {
    ROLLBACK_RAN="true"
    local code_backup="$BACKUP_DIR/dashboard-$TIMESTAMP.tar.gz"
    local db_backup="$BACKUP_DIR/duckdb-$TIMESTAMP.duckdb"
    local env_backup="$BACKUP_DIR/api-v1.env-$TIMESTAMP"
    local rollback_ok=1

    warn "════════════════════════════════════════════════════"
    warn "  ROLLBACK AUTOMÁTICO em andamento"
    warn "════════════════════════════════════════════════════"

    if [ ! -f "$code_backup" ]; then
        error "Backup de código não encontrado: $code_backup"
        error "ROLLBACK FAILED — intervenção manual necessária"
        exit 3
    fi

    # Para o api_service antes de mexer no código
    info "Parando unbound-dashboard-api..."
    systemctl stop unbound-dashboard-api 2>/dev/null || true

    info "Restaurando código a partir de $code_backup..."
    if tar xzf "$code_backup" -C /; then
        log "Código restaurado"
    else
        error "Falha ao restaurar código"
        rollback_ok=0
    fi

    if [ -f "$db_backup" ]; then
        info "Restaurando DuckDB a partir de $db_backup..."
        # O .wal atual é da versão nova: reproduzido sobre o snapshot antigo
        # corrompe ou impede a abertura. Restaura o WAL do snapshot (se havia)
        # ou tira o atual do caminho (preservado para diagnóstico).
        if [ -f "${DUCKDB_PATH}.wal" ]; then
            mv -f "${DUCKDB_PATH}.wal" "${DUCKDB_PATH}.wal.rollback-$TIMESTAMP" || rollback_ok=0
        fi
        if [ -f "${db_backup}.wal" ]; then
            cp -a "${db_backup}.wal" "${DUCKDB_PATH}.wal" || rollback_ok=0
        fi
        if cp -a "$db_backup" "$DUCKDB_PATH"; then
            log "DuckDB restaurado"
        else
            error "Falha ao restaurar DuckDB"
            rollback_ok=0
        fi
    fi

    if [ -f "$env_backup" ]; then
        info "Restaurando $ENV_FILE..."
        cp -a "$env_backup" "$ENV_FILE" || warn "Falha ao restaurar env"
    fi

    info "Reiniciando api_service após rollback..."
    systemctl start unbound-dashboard-api

    sleep 5
    if systemctl is-active --quiet unbound-dashboard-api \
       && curl -sf --max-time 5 http://127.0.0.1:8001/api/v1/healthz >/dev/null 2>&1; then
        log "api_service saudável após rollback"
    else
        error "api_service AINDA falha após rollback"
        rollback_ok=0
    fi

    if [ $rollback_ok -eq 1 ]; then
        warn "════════════════════════════════════════════════════"
        warn "  ROLLBACK CONCLUÍDO — sistema voltou à versão anterior"
        warn "════════════════════════════════════════════════════"
        exit 2
    else
        error "════════════════════════════════════════════════════"
        error "  ROLLBACK FAILED — estado inconsistente"
        error "  Backup íntegro em: $BACKUP_DIR/*-$TIMESTAMP*"
        error "  Intervenção manual necessária (SSH + restore manual)"
        error "════════════════════════════════════════════════════"
        exit 3
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
    APPLY_STARTED="true"
    apply_dashboard
    apply_apiservice
    apply_system
    reset_permissions
    restart_and_smoke
    UPDATE_DONE="true"
    print_report
}

main
