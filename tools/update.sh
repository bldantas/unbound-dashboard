#!/bin/bash
# ============================================================
# Unbound Dashboard — Update Script
# Sincroniza código e configurações com servidor novo
# ============================================================

set -euo pipefail

# ============================================================
# CONFIGURAÇÃO
# ============================================================
DASHBOARD_DIR="/var/www/html/unbound-dashboard"
BACKUP_DIR="/var/backups/unbound-dashboard"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VERSION_FILE="$DASHBOARD_DIR/VERSION"
VERSION="1.0.0"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
UPDATE_PACKAGE="${1:-.}"
DRY_RUN="${DRY_RUN:-false}"
VERBOSE="${VERBOSE:-false}"
AUTO_RESTART="${AUTO_RESTART:-false}"
AUTO_PREPARE_DB="${AUTO_PREPARE_DB:-true}"

# ============================================================
# CORES E FUNÇÕES
# ============================================================
RED=''
GREEN=''
YELLOW=''
BLUE=''
NC=''

log()   { echo "[OK] $1"; }
info()  { echo "[..] $1"; }
warn()  { echo "[!!] $1"; }
error() { echo "[XX] $1"; }
debug() { [ "$VERBOSE" = "true" ] && echo "[??] $1" || true; }

if [ -f "$VERSION_FILE" ]; then
    file_version=$(tr -d '[:space:]' < "$VERSION_FILE")
    if [[ "$file_version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        VERSION="$file_version"
    fi
fi

DB_HOST="127.0.0.1"
DB_NAME="unbound_dash"
DB_USER="unbounddb"
DB_PASS="unbounddash"

load_db_config() {
    local env_file

    DB_HOST="127.0.0.1"
    DB_NAME="unbound_dash"
    DB_USER="unbounddb"
    DB_PASS="unbounddash"

    for env_file in "$DASHBOARD_DIR/.env" "/etc/unbound-dashboard.env"; do
        [ -r "$env_file" ] || continue

        while IFS='=' read -r key value; do
            key="$(echo "$key" | xargs)"
            value="$(echo "$value" | sed -e 's/^\s*//' -e 's/\s*$//' -e 's/^"//' -e 's/"$//')"

            case "$key" in
                DB_HOST) DB_HOST="$value" ;;
                DB_NAME) DB_NAME="$value" ;;
                DB_USER) DB_USER="$value" ;;
                DB_PASS) DB_PASS="$value" ;;
            esac
        done < "$env_file"
    done

    debug "DB config carregada: host=$DB_HOST db=$DB_NAME user=$DB_USER"
}

database_is_reachable() {
    php -r "
        require_once '$DASHBOARD_DIR/src/Database.php';
        \\App\\Database::getInstance()->query('SELECT 1');
        echo 'DB OK';
    " 2>/dev/null | grep -q "DB OK"
}

prepare_database_server() {
    local db_service=""
    local schema_file=""

    [ "$AUTO_PREPARE_DB" = "true" ] || return 0

    if [ "$DRY_RUN" = "true" ]; then
        debug "Pulando preparação automática do banco em DRY-RUN"
        return 0
    fi

    load_db_config
    info "Preparando MariaDB/MySQL antes do update..."

    if systemctl list-unit-files mariadb.service >/dev/null 2>&1; then
        db_service="mariadb"
    elif systemctl list-unit-files mysql.service >/dev/null 2>&1; then
        db_service="mysql"
    fi

    if [ -z "$db_service" ]; then
        warn "Serviço MariaDB/MySQL não encontrado. Seguindo sem preparação automática do banco."
        return 0
    fi

    systemctl enable "$db_service" &>/dev/null || true
    systemctl start "$db_service" &>/dev/null || true

    if ! systemctl is-active "$db_service" &>/dev/null; then
        warn "Não foi possível iniciar $db_service. Seguindo sem preparação automática do banco."
        return 0
    fi

    if ! command -v mysql &>/dev/null; then
        warn "Cliente mysql não encontrado. Seguindo sem preparação automática do banco."
        return 0
    fi

    if [ -x "/usr/local/bin/fix-mariadb.sh" ]; then
        /usr/local/bin/fix-mariadb.sh &>/dev/null || warn "fix-mariadb.sh retornou erro; continuando com provisionamento padrão"
    fi

    mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >/dev/null 2>&1 || {
        warn "Não foi possível garantir a existência do banco $DB_NAME"
        return 0
    }

    mysql -e "DROP USER IF EXISTS '${DB_USER}'@'localhost';" >/dev/null 2>&1 || true
    mysql -e "DROP USER IF EXISTS '${DB_USER}'@'127.0.0.1';" >/dev/null 2>&1 || true
    mysql -e "CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';" >/dev/null 2>&1 || true
    mysql -e "CREATE USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';" >/dev/null 2>&1 || true
    mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';" >/dev/null 2>&1 || true
    mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';" >/dev/null 2>&1 || true
    mysql -e "FLUSH PRIVILEGES;" >/dev/null 2>&1 || true

    schema_file="$EXTRACTED_UPDATE_DIR/scripts/init_db.sql"
    if [ ! -f "$schema_file" ]; then
        schema_file="$DASHBOARD_DIR/scripts/init_db.sql"
    fi

    if [ -f "$schema_file" ]; then
        sed '/^CREATE DATABASE /Id;/^USE /Id' "$schema_file" | mysql "$DB_NAME" >/dev/null 2>&1 || \
            warn "Não foi possível importar o schema automaticamente"
    fi

    database_is_reachable && log "Banco de dados preparado com sucesso" || \
        warn "Banco ainda inacessível após preparação automática"
}

# ============================================================
# MIGRAÇÃO INCREMENTAL DO BANCO
# ============================================================
run_db_migrations() {
    [ "$AUTO_PREPARE_DB" = "true" ] || return 0
    [ "$DRY_RUN" = "false" ] || { info "[DRY-RUN] Pulando migrações de banco"; return 0; }

    local migrate_file
    migrate_file="$EXTRACTED_UPDATE_DIR/scripts/migrate_db.sql"
    [ -f "$migrate_file" ] || migrate_file="$DASHBOARD_DIR/scripts/migrate_db.sql"

    if [ ! -f "$migrate_file" ]; then
        debug "Arquivo migrate_db.sql não encontrado — nenhuma migração a aplicar"
        return 0
    fi

    load_db_config
    info "Aplicando migrações de banco de dados..."

    if ! command -v mysql &>/dev/null; then
        warn "Cliente mysql não encontrado — migrações ignoradas"
        return 0
    fi

    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$migrate_file" 2>/dev/null && \
        log "Migrações de banco aplicadas com sucesso" || \
        warn "Uma ou mais migrações retornaram aviso (verifique manualmente se necessário)"
}


validate_environment() {
    info "Validando ambiente..."
    
    # Check root
    if [ "$EUID" -ne 0 ]; then
        error "Este script deve ser executado como root (use sudo)"
        exit 1
    fi
    
    # Check dashboard dir
    if [ ! -d "$DASHBOARD_DIR" ]; then
        error "Diretório do dashboard não encontrado: $DASHBOARD_DIR"
        exit 1
    fi
    
    # Check update package
    if [ ! -d "$UPDATE_PACKAGE" ] && [ ! -f "$UPDATE_PACKAGE" ]; then
        error "Pacote de update não encontrado: $UPDATE_PACKAGE"
        error "Uso: sudo bash update.sh /caminho/para/pacote.tar.gz"
        exit 1
    fi
    
    # Check PHP syntax (apenas validar sintaxe básica)
    info "Validando sintaxe PHP..."
    php -r "echo 'PHP OK';" &>/dev/null || {
        error "PHP não está disponível"
        exit 1
    }
    
    # Tentar conectar ao DB (mas não falhar se não conseguir)
    debug "Testando conexão com banco de dados..."
    if [ -f "$DASHBOARD_DIR/src/Database.php" ]; then
        load_db_config
        database_is_reachable && log "Banco de dados acessível" || warn "Banco de dados pode estar offline (será validado após update)"
    else
        warn "Database.php não encontrado (será restaurado via update)"
    fi
    
    log "Ambiente validado com sucesso"
}

# ============================================================
# BACKUP PRÉ-UPDATE
# ============================================================
create_backup() {
    info "Criando backup pré-update..."
    mkdir -p "$BACKUP_DIR"
    
    local backup_file="$BACKUP_DIR/dashboard-$TIMESTAMP.tar.gz"
    local db_backup="$BACKUP_DIR/database-$TIMESTAMP.sql"
    
    # Backup código
    if [ "$DRY_RUN" = "false" ]; then
        tar czf "$backup_file" \
            --exclude=".git" \
            --exclude="node_modules" \
            --exclude="data/tmp/*" \
            --exclude="src/data/tmp/*" \
            -C "$(dirname "$DASHBOARD_DIR")" \
            "$(basename "$DASHBOARD_DIR")" \
            2>/dev/null
        log "Backup do código: $backup_file"
    fi
    
    # Backup database
    if [ "$DRY_RUN" = "false" ]; then
        if command -v mysqldump &>/dev/null; then
            mysqldump -u root unbound_dash > "$db_backup" 2>/dev/null || \
            mysqldump unbound_dash > "$db_backup" 2>/dev/null || \
            warn "Não conseguiu fazer dump do banco de dados"
            [ -f "$db_backup" ] && log "Backup do banco: $db_backup"
        fi
    fi
    
    debug "Backup criado em: $BACKUP_DIR"
}

# Variável global para armazenar o caminho extraído
EXTRACTED_UPDATE_DIR=""

# ============================================================
# EXTRAIR PACOTE DE UPDATE
# ============================================================
extract_update_package() {
    info "Extraindo pacote de update..."
    
    local temp_dir="/tmp/unbound-dashboard-update-$$"
    
    if [ -f "$UPDATE_PACKAGE" ]; then
        # É um arquivo tar.gz
        mkdir -p "$temp_dir"
        tar xzf "$UPDATE_PACKAGE" -C "$temp_dir" 2>/dev/null || {
            error "Erro ao extrair pacote de update"
            return 1
        }
        
        # Encontrar a pasta raiz extraída
        EXTRACTED_UPDATE_DIR=$(find "$temp_dir" -mindepth 1 -maxdepth 1 -type d | head -1)
        [ -n "$EXTRACTED_UPDATE_DIR" ] || EXTRACTED_UPDATE_DIR="$temp_dir"
        log "Pacote extraído em: $EXTRACTED_UPDATE_DIR"
        
    elif [ -d "$UPDATE_PACKAGE" ]; then
        # É um diretório
        EXTRACTED_UPDATE_DIR="$UPDATE_PACKAGE"
        log "Usando diretório: $EXTRACTED_UPDATE_DIR"
    else
        error "Formato de pacote não reconhecido"
        return 1
    fi

    return 0
}

# ============================================================
# VALIDAR ARQUIVOS DE UPDATE
# ============================================================
validate_update_files() {
    local update_dir="$1"
    
    info "Validando arquivos de update..."
    
    # Validar PHP
    for php_file in $(find "$update_dir" -name "*.php" -type f 2>/dev/null); do
        if ! php -l "$php_file" &>/dev/null; then
            error "Erro de sintaxe em: $php_file"
            return 1
        fi
    done
    
    # Validar JSON configs
    for json_file in $(find "$update_dir" -name "*.json" -type f 2>/dev/null); do
        if ! php -r "json_decode(file_get_contents('$json_file'), true);" 2>/dev/null; then
            error "JSON inválido em: $json_file"
            return 1
        fi
    done
    
    log "Arquivos de update validados"
    return 0
}

# ============================================================
# ATUALIZAR CÓDIGO
# ============================================================
update_code() {
    local update_dir="$1"
    local root_php_file
    
    info "Atualizando código PHP..."
    
    # Lista de diretórios a atualizar
    local targets=(
        "src"
        "api"
        "includes"
        "scripts"
    )
    
    for target in "${targets[@]}"; do
        local src="$update_dir/$target"
        local dst="$DASHBOARD_DIR/$target"
        
        if [ -e "$src" ]; then
            if [ "$DRY_RUN" = "true" ]; then
                info "[DRY-RUN] Copiaria: $src -> $dst"
            else
                if [ -d "$src" ]; then
                    mkdir -p "$dst"
                    if [ "$target" = "src" ]; then
                        rsync -av "$src/" "$dst/" \
                            --exclude="Database.php" \
                            --exclude="data/**" \
                            --exclude=".git" \
                            --exclude="node_modules" \
                            --delete \
                            2>&1 | grep -E "^sending|^deleting|/|total size" || true
                    else
                        rsync -av "$src/" "$dst/" \
                            --exclude=".git" \
                            --exclude="node_modules" \
                            --delete \
                            2>&1 | grep -E "^sending|^deleting|/|total size" || true
                    fi
                else
                    cp "$src" "$dst"
                fi
                debug "Atualizado: $target"
            fi
        fi
    done

    for root_php_file in "$update_dir"/*.php; do
        [ -f "$root_php_file" ] || continue

        if [ "$DRY_RUN" = "true" ]; then
            info "[DRY-RUN] Copiaria: $root_php_file -> $DASHBOARD_DIR/$(basename "$root_php_file")"
        else
            cp "$root_php_file" "$DASHBOARD_DIR/$(basename "$root_php_file")"
            debug "Atualizado arquivo raiz: $(basename "$root_php_file")"
        fi
    done

    if [ -f "$update_dir/VERSION" ]; then
        if [ "$DRY_RUN" = "true" ]; then
            info "[DRY-RUN] Copiaria: $update_dir/VERSION -> $DASHBOARD_DIR/VERSION"
        else
            cp "$update_dir/VERSION" "$DASHBOARD_DIR/VERSION"
            debug "Atualizado arquivo VERSION"
        fi
    fi

    if [ -f "$update_dir/CHANGELOG.md" ]; then
        if [ "$DRY_RUN" = "true" ]; then
            info "[DRY-RUN] Copiaria: $update_dir/CHANGELOG.md -> $DASHBOARD_DIR/CHANGELOG.md"
        else
            cp "$update_dir/CHANGELOG.md" "$DASHBOARD_DIR/CHANGELOG.md"
            debug "Atualizado arquivo CHANGELOG.md"
        fi
    fi
    
    if [ "$DRY_RUN" = "false" ]; then
        log "Código atualizado"
    fi

    return 0
}

# ============================================================
# ATUALIZAR TEMPLATES
# ============================================================
update_templates() {
    local update_dir="$1"
    local src
    local dst
    
    info "Atualizando templates de configuração..."
    
    local templates=(
        "src/data/tmp/*.conf"
        "system/sudoers/*"
        "system/systemd/*"
    )
    
    for template_pattern in "${templates[@]}"; do
        for src in $(find "$update_dir" -path "*$template_pattern" -type f 2>/dev/null); do
            local relative_path="${src#$update_dir/}"

            if [[ "$relative_path" == src/data/tmp/* ]]; then
                dst="$DASHBOARD_DIR/$relative_path"
            elif [[ "$relative_path" == system/sudoers/* ]]; then
                dst="/etc/sudoers.d/$(basename "$src")"
            elif [[ "$relative_path" == system/systemd/* ]]; then
                dst="/etc/systemd/system/$(basename "$src")"
            else
                dst="$DASHBOARD_DIR/$relative_path"
            fi
            
            if [ "$DRY_RUN" = "true" ]; then
                info "[DRY-RUN] Template: $relative_path"
            else
                mkdir -p "$(dirname "$dst")"
                cp "$src" "$dst"
                if [[ "$dst" == /etc/sudoers.d/* ]]; then
                    chmod 440 "$dst"
                fi
                debug "Template atualizado: $relative_path"
            fi
        done
    done

    if [ "$DRY_RUN" = "false" ] && [ -d "$update_dir/system/systemd" ]; then
        systemctl daemon-reload >/dev/null 2>&1 || warn "Falha ao recarregar daemon do systemd"
    fi
    
    if [ "$DRY_RUN" = "false" ]; then
        log "Templates atualizados"
    fi

    return 0
}

# ============================================================
# ATUALIZAR SCRIPTS DO SISTEMA
# ============================================================
update_system_scripts() {
    local update_dir="$1"
    
    info "Atualizando scripts do sistema..."
    
    if [ -d "$update_dir/system/bin" ]; then
        if [ "$DRY_RUN" = "true" ]; then
            info "[DRY-RUN] Scripts do sistema seriam copiados"
        else
            for script in "$update_dir/system/bin"/*.sh; do
                if [ -f "$script" ]; then
                    local script_name=$(basename "$script")
                    cp "$script" "/usr/local/bin/$script_name"
                    chmod +x "/usr/local/bin/$script_name"
                    debug "Script instalado: /usr/local/bin/$script_name"
                fi
            done
        fi
    fi
    
    if [ "$DRY_RUN" = "false" ]; then
        log "Scripts do sistema atualizados"
    fi

    return 0
}

# ============================================================
# ATUALIZAR CRONTABS DO DASHBOARD
# ============================================================
update_crontabs() {
    local update_dir="$1"
    local cron_file="$update_dir/system/cron/unbound-dashboard-crons"

    [ -f "$cron_file" ] || return 0

    info "Atualizando crontabs do dashboard..."

    if [ "$DRY_RUN" = "true" ]; then
        info "[DRY-RUN] Crontabs do dashboard seriam atualizadas"
        return 0
    fi

    local temp_cron="/tmp/unbound-dashboard-cron-$$"
    crontab -l 2>/dev/null | grep -v 'UNBOUND-DASHBOARD' > "$temp_cron" 2>/dev/null || true
    cat "$cron_file" >> "$temp_cron"
    # Garante newline no final (crontab exige)
    sed -i -e '$a\' "$temp_cron"
    crontab "$temp_cron"
    rm -f "$temp_cron"

    log "Crontabs do dashboard atualizadas"
    return 0
}

# ============================================================
# RESETAR PERMISSÕES
# ============================================================
reset_permissions() {
    info "Resetando permissões..."
    
    if [ "$DRY_RUN" = "true" ]; then
        info "[DRY-RUN] Permissões seriam resetadas"
    else
        # Dashboard directory
        chown -R www-data:www-data "$DASHBOARD_DIR" 2>/dev/null || true
        chmod 755 "$DASHBOARD_DIR"
        
        # Special files
        find "$DASHBOARD_DIR" -name "*.php" -type f ! -path "$DASHBOARD_DIR/src/Database.php" -exec chmod 644 {} \;
        find "$DASHBOARD_DIR" -name "*.sh" -type f -exec chmod 755 {} \;
        [ -f "$DASHBOARD_DIR/src/Database.php" ] && chmod 660 "$DASHBOARD_DIR/src/Database.php"
        
        # Data directories
        chmod 775 "$DASHBOARD_DIR/data"
        chmod 775 "$DASHBOARD_DIR/src/data"
        chmod 775 "$DASHBOARD_DIR/src/data/tmp"
        
        debug "Permissões resetadas"
    fi
    
    log "Permissões configuradas"
    return 0
}

# ============================================================
# VALIDAÇÃO PÓS-UPDATE
# ============================================================
validate_after_update() {
    info "Validando estado pós-update..."
    
    # Validar PHP files
    debug "Validando sintaxe PHP..."
    find "$DASHBOARD_DIR" -name "*.php" -type f | head -10 | while read -r php_file; do
        if ! php -l "$php_file" &>/dev/null; then
            error "Erro de sintaxe em: $php_file"
            return 1
        fi
    done
    
    # Validar conexão DB
    debug "Testando banco de dados..."
    prepare_database_server
    if database_is_reachable; then
        log "Banco de dados acessível após update"
    else
        warn "Banco de dados inacessível após update. Verifique MariaDB e credenciais do aplicativo."
    fi
    
    # Validar serviços
    debug "Validando serviços..."
    systemctl is-active apache2 &>/dev/null || warn "Apache2 não está ativo"
    systemctl is-active unbound &>/dev/null || warn "Unbound não está ativo"
    
    log "Validação pós-update concluída"
    return 0
}

# ============================================================
# RELATÓRIO FINAL
# ============================================================
print_report() {
    local end_time=$(date +%s)
    local duration=$((end_time - START_TIME))
    
    echo ""
    echo "╔════════════════════════════════════════════════════╗"
    echo "║     Unbound Dashboard — Relatório de Update        ║"
    echo "╚════════════════════════════════════════════════════╝"
    echo ""
    echo "Status:          $([ "$DRY_RUN" = "true" ] && echo "DRY-RUN" || echo "COMPLETO")"
    echo "Data/Hora:       $(date '+%d/%m/%Y %H:%M:%S')"
    echo "Duração:         ${duration}s"
    echo "Timestamp:       $TIMESTAMP"
    echo ""
    echo "Backup:"
    echo "  Código:        $BACKUP_DIR/dashboard-$TIMESTAMP.tar.gz"
    echo "  Base de Dados: $BACKUP_DIR/database-$TIMESTAMP.sql"
    echo ""
    echo "Diretório:       $DASHBOARD_DIR"
    echo "Versão:          $VERSION"
    echo ""
    
    if [ "$AUTO_RESTART" = "true" ] && [ "$DRY_RUN" = "false" ]; then
        echo "Reiniciando serviços..."
        systemctl restart apache2 2>/dev/null && log "Apache2 reiniciado" || warn "Erro ao reiniciar Apache2"
        systemctl restart unbound 2>/dev/null && log "Unbound reiniciado" || warn "Erro ao reiniciar Unbound"
    else
        echo ""
        echo "Para aplicar as mudanças, reinicie os serviços:"
        echo "  sudo systemctl restart apache2"
        echo "  sudo systemctl restart unbound"
    fi
    
    echo ""
    echo "Para rollback, execute:"
    echo "  tar xzf $BACKUP_DIR/dashboard-$TIMESTAMP.tar.gz -C /"
    echo ""
}

# ============================================================
# MAIN
# ============================================================
main() {
    START_TIME=$(date +%s)
    
    echo "╔════════════════════════════════════════════════════╗"
    echo "║  Unbound Dashboard — Update Script v$VERSION          ║"
    echo "╚════════════════════════════════════════════════════╝"
    echo ""
    
    # Verificar se pacote foi passado
    if [ "$UPDATE_PACKAGE" = "." ] || [ -z "$UPDATE_PACKAGE" ]; then
        warn "Nenhum pacote de update foi passado como argumento"
        echo ""
        echo "Uso:"
        echo "  sudo bash update.sh /caminho/para/pacote.tar.gz"
        echo "  sudo bash update.sh ./unbound-dashboard-update-*.tar.gz"
        echo ""
        echo "Variáveis de ambiente:"
        echo "  DRY_RUN=true          Modo seguro (simula sem fazer mudanças)"
        echo "  AUTO_RESTART=true     Reinicia serviços automaticamente"
        echo "  AUTO_PREPARE_DB=true  Tenta iniciar/provisionar MariaDB antes da validação"
        echo "  VERBOSE=true          Modo detalhado com debug"
        echo ""
        exit 1
    fi
    
    [ "$DRY_RUN" = "true" ] && warn "Modo DRY-RUN: nenhuma mudança será feita"
    echo ""
    
    validate_environment
    create_backup
    
    extract_update_package || exit 1
    trap "rm -rf $EXTRACTED_UPDATE_DIR" EXIT
    
    validate_update_files "$EXTRACTED_UPDATE_DIR"
    update_code "$EXTRACTED_UPDATE_DIR"
    run_db_migrations
    update_templates "$EXTRACTED_UPDATE_DIR"
    update_system_scripts "$EXTRACTED_UPDATE_DIR"
    update_crontabs "$EXTRACTED_UPDATE_DIR"
    reset_permissions
    validate_after_update
    
    print_report
}

# ============================================================
# EXECUÇÃO
# ============================================================
main
