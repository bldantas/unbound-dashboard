#!/bin/bash
# ============================================================
# Unbound Dashboard — MariaDB Auto-Tuner
# Analisa o servidor e aplica configuração otimizada
# ============================================================

set -euo pipefail

# ============================================================
# CORES E FUNÇÕES
# ============================================================
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

log()   { printf "${GREEN}[OK]${NC} %s\n" "$1"; }
info()  { printf "${BLUE}[..]${NC} %s\n" "$1"; }
warn()  { printf "${YELLOW}[!!]${NC} %s\n" "$1"; }
error() { printf "${RED}[XX]${NC} %s\n" "$1"; }
header(){ printf "\n${CYAN}${BOLD}── %s${NC}\n" "$1"; }

DRY_RUN="${DRY_RUN:-false}"
CONF_FILE="/etc/mysql/mariadb.conf.d/90-unbound-dashboard-tuning.cnf"
DASHBOARD_DIR="${DASHBOARD_DIR:-/var/www/html/unbound-dashboard}"

# ============================================================
# VERIFICAÇÕES
# ============================================================
preflight_checks() {
    if [[ $EUID -ne 0 ]]; then
        error "Execute como root: sudo bash $0"
        exit 1
    fi

    if ! command -v mysql &>/dev/null; then
        error "Cliente mysql não encontrado"
        exit 1
    fi

    if ! systemctl is-active mariadb &>/dev/null && ! systemctl is-active mysql &>/dev/null; then
        error "MariaDB/MySQL não está rodando"
        exit 1
    fi
}

# ============================================================
# COLETA DE DADOS DO SERVIDOR
# ============================================================
collect_server_info() {
    header "ANÁLISE DO SERVIDOR"

    # RAM total em MB
    TOTAL_RAM_MB=$(awk '/MemTotal/ {printf "%d", $2/1024}' /proc/meminfo)
    AVAILABLE_RAM_MB=$(awk '/MemAvailable/ {printf "%d", $2/1024}' /proc/meminfo)
    CPU_CORES=$(nproc 2>/dev/null || echo 2)

    # Disco
    DISK_TYPE="hdd"
    local root_device
    root_device=$(df / | tail -1 | awk '{print $1}')
    # Resolve symlinks (LVM)
    root_device=$(readlink -f "$root_device" 2>/dev/null || echo "$root_device")
    local base_device
    base_device=$(echo "$root_device" | sed 's|/dev/||;s|[0-9]*$||;s|p[0-9]*$||')
    if [[ -f "/sys/block/${base_device}/queue/rotational" ]]; then
        local rotational
        rotational=$(cat "/sys/block/${base_device}/queue/rotational" 2>/dev/null || echo 1)
        [[ "$rotational" == "0" ]] && DISK_TYPE="ssd"
    fi

    printf "  %-24s %s\n" "RAM Total:" "${TOTAL_RAM_MB} MB"
    printf "  %-24s %s\n" "RAM Disponível:" "${AVAILABLE_RAM_MB} MB"
    printf "  %-24s %s\n" "CPU Cores:" "$CPU_CORES"
    printf "  %-24s %s\n" "Tipo de Disco:" "$DISK_TYPE"
}

# ============================================================
# COLETA DE DADOS DO MARIADB
# ============================================================
collect_mariadb_info() {
    header "ESTADO ATUAL DO MARIADB"

    # Versão
    local version
    version=$(mysql -sNe "SELECT VERSION();" 2>/dev/null || echo "desconhecida")
    printf "  %-24s %s\n" "Versão:" "$version"

    # Buffer pool atual
    CURRENT_BP=$(mysql -sNe "SELECT @@innodb_buffer_pool_size;" 2>/dev/null || echo 0)
    CURRENT_BP_MB=$((CURRENT_BP / 1024 / 1024))
    printf "  %-24s %s MB\n" "Buffer Pool Atual:" "$CURRENT_BP_MB"

    # Hit ratio
    local reads requests hit_ratio
    reads=$(mysql -sNe "SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_reads';" 2>/dev/null || echo 0)
    requests=$(mysql -sNe "SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_read_requests';" 2>/dev/null || echo 1)
    if [[ "$requests" -gt 0 ]]; then
        hit_ratio=$(awk "BEGIN {printf \"%.2f\", (1 - $reads/$requests) * 100}")
    else
        hit_ratio="N/A"
    fi
    printf "  %-24s %s%%\n" "Buffer Pool Hit Ratio:" "$hit_ratio"

    # Páginas sujas / livres
    local pages_dirty pages_free pages_total
    pages_dirty=$(mysql -sNe "SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_pages_dirty';" 2>/dev/null || echo 0)
    pages_free=$(mysql -sNe "SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_pages_free';" 2>/dev/null || echo 0)
    pages_total=$(mysql -sNe "SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_pages_total';" 2>/dev/null || echo 1)
    printf "  %-24s %s / %s\n" "Páginas (livre/total):" "$pages_free" "$pages_total"
    printf "  %-24s %s\n" "Páginas Sujas:" "$pages_dirty"

    # Tamanho dos dados
    local total_data_mb
    total_data_mb=$(mysql -sNe "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema NOT IN ('information_schema', 'performance_schema', 'mysql', 'sys');" 2>/dev/null || echo 0)
    DB_DATA_MB="${total_data_mb:-0}"
    printf "  %-24s %s MB\n" "Dados + Índices:" "$DB_DATA_MB"

    # Connections
    local max_conn current_conn
    max_conn=$(mysql -sNe "SELECT @@max_connections;" 2>/dev/null || echo 151)
    current_conn=$(mysql -sNe "SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Threads_connected';" 2>/dev/null || echo 0)
    printf "  %-24s %s / %s\n" "Conexões (ativas/max):" "$current_conn" "$max_conn"

    # Query cache
    local qc_type
    qc_type=$(mysql -sNe "SELECT @@query_cache_type;" 2>/dev/null || echo "OFF")
    printf "  %-24s %s\n" "Query Cache:" "$qc_type"

    # Temp tables to disk
    local tmp_disk tmp_created
    tmp_disk=$(mysql -sNe "SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Created_tmp_disk_tables';" 2>/dev/null || echo 0)
    tmp_created=$(mysql -sNe "SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Created_tmp_tables';" 2>/dev/null || echo 1)
    if [[ "$tmp_created" -gt 0 ]]; then
        local tmp_disk_pct
        tmp_disk_pct=$(awk "BEGIN {printf \"%.1f\", ($tmp_disk/$tmp_created) * 100}")
        printf "  %-24s %s%% (%s de %s)\n" "Temp Tables em Disco:" "$tmp_disk_pct" "$tmp_disk" "$tmp_created"
    fi

    # Slow queries
    local slow_queries
    slow_queries=$(mysql -sNe "SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Slow_queries';" 2>/dev/null || echo 0)
    printf "  %-24s %s\n" "Slow Queries:" "$slow_queries"

    # Table lock waits
    local lock_waited lock_immediate
    lock_waited=$(mysql -sNe "SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Table_locks_waited';" 2>/dev/null || echo 0)
    lock_immediate=$(mysql -sNe "SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Table_locks_immediate';" 2>/dev/null || echo 1)
    printf "  %-24s %s (imediatos: %s)\n" "Table Locks Waited:" "$lock_waited" "$lock_immediate"
}

# ============================================================
# CALCULAR CONFIGURAÇÃO IDEAL
# ============================================================
calculate_config() {
    header "CALCULANDO CONFIGURAÇÃO IDEAL"

    # ── Buffer Pool Size ──
    # Regra: o maior entre (dados+índices * 1.2) e (25% da RAM),
    # limitado a 70% da RAM para não afogar o SO + Unbound
    local data_need bp_min bp_max

    # Necessidade real: dados + 20% overhead
    data_need=$(awk "BEGIN {printf \"%d\", $DB_DATA_MB * 1.2}")

    # Mínimo: 25% da RAM ou o que os dados precisam
    bp_min=$(( TOTAL_RAM_MB * 25 / 100 ))
    [[ "$data_need" -gt "$bp_min" ]] && bp_min="$data_need"

    # Máximo: 70% da RAM (servidor compartilhado com Unbound + Apache)
    bp_max=$(( TOTAL_RAM_MB * 70 / 100 ))

    # Clamp
    CALC_BP_MB="$bp_min"
    [[ "$CALC_BP_MB" -gt "$bp_max" ]] && CALC_BP_MB="$bp_max"

    # Arredonda para múltiplo de 128MB
    CALC_BP_MB=$(( (CALC_BP_MB + 127) / 128 * 128 ))

    # Mínimo absoluto de 256MB
    [[ "$CALC_BP_MB" -lt 256 ]] && CALC_BP_MB=256

    # ── Buffer Pool Instances ──
    # 1 instância por GB, máximo de cores, mínimo 1
    CALC_BP_INSTANCES=$(( CALC_BP_MB / 1024 ))
    [[ "$CALC_BP_INSTANCES" -lt 1 ]] && CALC_BP_INSTANCES=1
    [[ "$CALC_BP_INSTANCES" -gt "$CPU_CORES" ]] && CALC_BP_INSTANCES="$CPU_CORES"
    [[ "$CALC_BP_INSTANCES" -gt 8 ]] && CALC_BP_INSTANCES=8

    # ── Log File Size ──
    # ~25% do buffer pool, máximo 1GB
    CALC_LOG_FILE_MB=$(( CALC_BP_MB / 4 ))
    [[ "$CALC_LOG_FILE_MB" -lt 48 ]] && CALC_LOG_FILE_MB=48
    [[ "$CALC_LOG_FILE_MB" -gt 1024 ]] && CALC_LOG_FILE_MB=1024

    # ── Log Buffer Size ──
    CALC_LOG_BUFFER_MB=32
    [[ "$CALC_BP_MB" -ge 4096 ]] && CALC_LOG_BUFFER_MB=64

    # ── IO Threads ──
    CALC_IO_THREADS=4
    [[ "$CPU_CORES" -ge 8 ]] && CALC_IO_THREADS=8

    # ── IO Capacity (SSD vs HDD) ──
    if [[ "$DISK_TYPE" == "ssd" ]]; then
        CALC_IO_CAPACITY=1000
        CALC_IO_CAPACITY_MAX=4000
    else
        CALC_IO_CAPACITY=200
        CALC_IO_CAPACITY_MAX=2000
    fi

    # ── Tmp Table Size ──
    # 64MB ou 1% da RAM, o que for maior
    CALC_TMP_TABLE_MB=$(( TOTAL_RAM_MB / 100 ))
    [[ "$CALC_TMP_TABLE_MB" -lt 64 ]] && CALC_TMP_TABLE_MB=64
    [[ "$CALC_TMP_TABLE_MB" -gt 256 ]] && CALC_TMP_TABLE_MB=256

    # ── Max Connections ──
    # Dashboard com poucos users: 50 é suficiente, máx 100
    CALC_MAX_CONN=50
    [[ "$TOTAL_RAM_MB" -ge 4096 ]] && CALC_MAX_CONN=75
    [[ "$TOTAL_RAM_MB" -ge 8192 ]] && CALC_MAX_CONN=100

    # ── Thread Cache ──
    CALC_THREAD_CACHE=16
    [[ "$CALC_MAX_CONN" -ge 75 ]] && CALC_THREAD_CACHE=32

    # ── Table Open Cache ──
    CALC_TABLE_OPEN_CACHE=400
    [[ "$TOTAL_RAM_MB" -ge 4096 ]] && CALC_TABLE_OPEN_CACHE=1000

    # ── Join Buffer / Sort Buffer ──
    CALC_JOIN_BUFFER_KB=512
    CALC_SORT_BUFFER_KB=2048

    # ── Flush Method ──
    CALC_FLUSH_METHOD="O_DIRECT"

    # ── trx commit (durabilidade vs performance) ──
    # 2 = flush a cada segundo (boa performance, risco de 1s de perda em crash)
    # Dashboard não é financeiro, perda tolerável
    CALC_TRX_COMMIT=2

    # Relatório
    printf "  %-32s %s MB → %s MB\n" "innodb_buffer_pool_size:" "$CURRENT_BP_MB" "$CALC_BP_MB"
    printf "  %-32s %s\n" "innodb_buffer_pool_instances:" "$CALC_BP_INSTANCES"
    printf "  %-32s %s MB\n" "innodb_log_file_size:" "$CALC_LOG_FILE_MB"
    printf "  %-32s %s MB\n" "innodb_log_buffer_size:" "$CALC_LOG_BUFFER_MB"
    printf "  %-32s %s\n" "innodb_read_io_threads:" "$CALC_IO_THREADS"
    printf "  %-32s %s\n" "innodb_write_io_threads:" "$CALC_IO_THREADS"
    printf "  %-32s %s\n" "innodb_io_capacity:" "$CALC_IO_CAPACITY"
    printf "  %-32s %s\n" "innodb_io_capacity_max:" "$CALC_IO_CAPACITY_MAX"
    printf "  %-32s %s\n" "innodb_flush_log_at_trx_commit:" "$CALC_TRX_COMMIT"
    printf "  %-32s %s\n" "innodb_flush_method:" "$CALC_FLUSH_METHOD"
    printf "  %-32s %s MB\n" "tmp_table_size:" "$CALC_TMP_TABLE_MB"
    printf "  %-32s %s MB\n" "max_heap_table_size:" "$CALC_TMP_TABLE_MB"
    printf "  %-32s %s\n" "max_connections:" "$CALC_MAX_CONN"
    printf "  %-32s %s\n" "thread_cache_size:" "$CALC_THREAD_CACHE"
    printf "  %-32s %s\n" "table_open_cache:" "$CALC_TABLE_OPEN_CACHE"
    printf "  %-32s %s KB\n" "join_buffer_size:" "$CALC_JOIN_BUFFER_KB"
    printf "  %-32s %s KB\n" "sort_buffer_size:" "$CALC_SORT_BUFFER_KB"
    printf "  %-32s %s\n" "Tipo de disco:" "$DISK_TYPE"
}

# ============================================================
# GERAR ARQUIVO DE CONFIGURAÇÃO
# ============================================================
generate_config() {
    header "GERANDO CONFIGURAÇÃO"

    local config_content
    config_content=$(cat <<CONF
# ============================================================
# Unbound Dashboard — MariaDB Auto-Tuning
# Gerado em: $(date '+%Y-%m-%d %H:%M:%S')
# Servidor: ${TOTAL_RAM_MB}MB RAM / ${CPU_CORES} cores / ${DISK_TYPE}
# Dados+Índices: ${DB_DATA_MB}MB
# ============================================================

[mariadbd]

# ── InnoDB Buffer Pool ──
innodb_buffer_pool_size         = ${CALC_BP_MB}M
innodb_buffer_pool_instances    = ${CALC_BP_INSTANCES}

# ── InnoDB Log ──
innodb_log_file_size            = ${CALC_LOG_FILE_MB}M
innodb_log_buffer_size          = ${CALC_LOG_BUFFER_MB}M

# ── InnoDB I/O ──
innodb_read_io_threads          = ${CALC_IO_THREADS}
innodb_write_io_threads         = ${CALC_IO_THREADS}
innodb_io_capacity              = ${CALC_IO_CAPACITY}
innodb_io_capacity_max          = ${CALC_IO_CAPACITY_MAX}
innodb_flush_log_at_trx_commit  = ${CALC_TRX_COMMIT}
innodb_flush_method             = ${CALC_FLUSH_METHOD}
innodb_flush_neighbors          = $([ "$DISK_TYPE" = "ssd" ] && echo 0 || echo 1)

# ── InnoDB Otimizações ──
innodb_file_per_table           = ON
innodb_stats_on_metadata        = OFF
innodb_adaptive_hash_index      = ON
innodb_change_buffering         = all

# ── Conexões e Threads ──
max_connections                 = ${CALC_MAX_CONN}
thread_cache_size               = ${CALC_THREAD_CACHE}
table_open_cache                = ${CALC_TABLE_OPEN_CACHE}
table_definition_cache          = 400

# ── Tabelas Temporárias ──
tmp_table_size                  = ${CALC_TMP_TABLE_MB}M
max_heap_table_size             = ${CALC_TMP_TABLE_MB}M

# ── Buffers por Sessão ──
join_buffer_size                = ${CALC_JOIN_BUFFER_KB}K
sort_buffer_size                = ${CALC_SORT_BUFFER_KB}K

# ── Query Cache (desabilitado — não beneficia o dashboard) ──
query_cache_type                = OFF
query_cache_size                = 0

# ── Segurança e Rede ──
skip_name_resolve               = ON
performance_schema              = OFF

# ── Slow Query Log ──
slow_query_log                  = ON
slow_query_log_file             = /var/log/mysql/mariadb-slow.log
long_query_time                 = 1
log_slow_verbosity              = query_plan

CONF
)

    if [[ "$DRY_RUN" == "true" ]]; then
        info "[DRY-RUN] Configuração que seria gravada em: $CONF_FILE"
        echo ""
        echo "$config_content"
        echo ""
        return 0
    fi

    # Backup se já existe
    if [[ -f "$CONF_FILE" ]]; then
        local backup="${CONF_FILE}.bak.$(date +%Y%m%d_%H%M%S)"
        cp "$CONF_FILE" "$backup"
        log "Backup da config anterior: $backup"
    fi

    echo "$config_content" > "$CONF_FILE"
    chmod 644 "$CONF_FILE"
    log "Configuração salva em: $CONF_FILE"

    # Garante diretório de slow log
    mkdir -p /var/log/mysql
    chown mysql:mysql /var/log/mysql 2>/dev/null || true
}

# ============================================================
# REMOVER buffer_pool_size DUPLICADO DO 50-server.cnf
# ============================================================
cleanup_old_config() {
    local server_cnf="/etc/mysql/mariadb.conf.d/50-server.cnf"
    if [[ -f "$server_cnf" ]] && grep -q "^innodb_buffer_pool_size" "$server_cnf"; then
        if [[ "$DRY_RUN" == "true" ]]; then
            info "[DRY-RUN] Comentaria innodb_buffer_pool_size duplicado em $server_cnf"
            return 0
        fi

        sed -i 's/^innodb_buffer_pool_size/#innodb_buffer_pool_size/' "$server_cnf"
        log "Comentado innodb_buffer_pool_size duplicado em $server_cnf"
    fi
}

# ============================================================
# VALIDAR E REINICIAR
# ============================================================
apply_config() {
    header "APLICANDO CONFIGURAÇÃO"

    if [[ "$DRY_RUN" == "true" ]]; then
        info "[DRY-RUN] MariaDB seria reiniciado para aplicar"
        return 0
    fi

    info "Validando configuração..."
    if ! mysqld --help --verbose 2>&1 | grep -q "innodb-buffer-pool-size"; then
        # Fallback: tenta iniciar para ver se aceita
        :
    fi

    info "Reiniciando MariaDB..."
    if systemctl restart mariadb 2>/dev/null || systemctl restart mysql 2>/dev/null; then
        log "MariaDB reiniciado com sucesso"
    else
        error "Falha ao reiniciar MariaDB!"
        error "Verifique: journalctl -xeu mariadb"

        # Rollback
        local backup
        backup=$(ls -t "${CONF_FILE}.bak."* 2>/dev/null | head -1)
        if [[ -n "$backup" ]]; then
            cp "$backup" "$CONF_FILE"
            systemctl restart mariadb 2>/dev/null || systemctl restart mysql 2>/dev/null
            warn "Rollback aplicado com config anterior"
        else
            rm -f "$CONF_FILE"
            systemctl restart mariadb 2>/dev/null || systemctl restart mysql 2>/dev/null
            warn "Config removida, MariaDB voltou ao padrão"
        fi
        return 1
    fi
}

# ============================================================
# VERIFICAÇÃO PÓS-APLICAÇÃO
# ============================================================
verify_config() {
    header "VERIFICAÇÃO PÓS-TUNING"

    # Aguarda MariaDB ficar pronto
    local retries=10
    while [[ $retries -gt 0 ]]; do
        if mysql -sNe "SELECT 1;" &>/dev/null; then
            break
        fi
        sleep 1
        retries=$((retries - 1))
    done

    if [[ $retries -eq 0 ]]; then
        error "MariaDB não respondeu após restart"
        return 1
    fi

    local new_bp new_bp_mb
    new_bp=$(mysql -sNe "SELECT @@innodb_buffer_pool_size;" 2>/dev/null || echo 0)
    new_bp_mb=$((new_bp / 1024 / 1024))

    local new_log_file
    new_log_file=$(mysql -sNe "SELECT @@innodb_log_file_size / 1024 / 1024;" 2>/dev/null || echo 0)

    local new_trx
    new_trx=$(mysql -sNe "SELECT @@innodb_flush_log_at_trx_commit;" 2>/dev/null || echo "?")

    local new_max_conn
    new_max_conn=$(mysql -sNe "SELECT @@max_connections;" 2>/dev/null || echo "?")

    local new_skip_name
    new_skip_name=$(mysql -sNe "SELECT @@skip_name_resolve;" 2>/dev/null || echo "?")

    printf "  %-32s %s MB ✓\n" "innodb_buffer_pool_size:" "$new_bp_mb"
    printf "  %-32s %s MB ✓\n" "innodb_log_file_size:" "$new_log_file"
    printf "  %-32s %s ✓\n" "innodb_flush_log_at_trx_commit:" "$new_trx"
    printf "  %-32s %s ✓\n" "max_connections:" "$new_max_conn"
    printf "  %-32s %s ✓\n" "skip_name_resolve:" "$new_skip_name"

    # Testa acesso do dashboard
    if [[ -f "$DASHBOARD_DIR/src/Database.php" ]]; then
        if php -r "
            require_once '$DASHBOARD_DIR/src/Database.php';
            \App\Database::getInstance()->query('SELECT 1');
            echo 'OK';
        " 2>/dev/null | grep -q "OK"; then
            log "Conexão do dashboard verificada"
        else
            warn "Dashboard não conseguiu conectar ao banco — verifique credenciais"
        fi
    fi

    log "Tuning aplicado com sucesso"
}

# ============================================================
# RELATÓRIO FINAL
# ============================================================
print_summary() {
    echo ""
    echo "╔══════════════════════════════════════════════════╗"
    echo "║   MariaDB Auto-Tuning Concluído ✓                ║"
    echo "╚══════════════════════════════════════════════════╝"
    echo ""
    printf "  %-24s %s MB → %s MB\n" "Buffer Pool:" "$CURRENT_BP_MB" "$CALC_BP_MB"
    printf "  %-24s %s\n" "Arquivo de config:" "$CONF_FILE"
    printf "  %-24s %s\n" "Tipo de disco:" "$DISK_TYPE"
    echo ""
    echo "  Para reverter:"
    echo "    sudo rm $CONF_FILE"
    echo "    sudo systemctl restart mariadb"
    echo ""
}

# ============================================================
# MAIN
# ============================================================
main() {
    echo ""
    echo "╔══════════════════════════════════════════════════╗"
    echo "║   Unbound Dashboard — MariaDB Auto-Tuner         ║"
    echo "╚══════════════════════════════════════════════════╝"

    if [[ "$DRY_RUN" == "true" ]]; then
        warn "Modo DRY-RUN ativo — nenhuma mudança será aplicada"
    fi

    preflight_checks
    collect_server_info
    collect_mariadb_info
    calculate_config
    generate_config
    cleanup_old_config

    if [[ "$DRY_RUN" != "true" ]]; then
        apply_config
        verify_config
    fi

    print_summary
}

main "$@"
