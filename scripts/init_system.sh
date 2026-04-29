#!/bin/bash

# ================================================================
# Script de Inicialização Pós-Instalação
# Unbound Dashboard
# ================================================================
# Uso: sudo bash scripts/init_system.sh
# Ou: bash scripts/init_system.sh (se tiver sudo sem senha)
# ================================================================

set -e

echo "╔════════════════════════════════════════════════════════╗"
echo "║   Inicialização do Sistema - Unbound Dashboard        ║"
echo "╚════════════════════════════════════════════════════════╝"
echo ""

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funções
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_ok() {
    echo -e "${GREEN}[OK]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[AVISO]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERRO]${NC} $1"
}

# ================================================================
# 1. DETERMINAR DIRETÓRIO DE INSTALAÇÃO
# ================================================================

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
DASHBOARD_DIR="$(dirname "$SCRIPT_DIR")"

log_info "Diretório do Dashboard: $DASHBOARD_DIR"

if [ ! -f "$DASHBOARD_DIR/src/Database.php" ]; then
    log_error "Arquivo Database.php não encontrado em $DASHBOARD_DIR/src/"
    log_error "Execute este script a partir do diretório raiz do dashboard."
    exit 1
fi

cd "$DASHBOARD_DIR"
log_ok "Diretório do dashboard validado"

# ================================================================
# 2. VERIFICAR E INSTALAR DEPENDÊNCIAS DO SISTEMA
# ================================================================

log_info "Verificando dependências do sistema..."

MISSING_DEPS=()

# Verificar se os comandos necessários existem
for cmd in php mysql unbound unbound-control unbound-checkconf sudo; do
    if ! command -v "$cmd" &> /dev/null; then
        MISSING_DEPS+=("$cmd")
    else
        log_ok "✓ $cmd encontrado"
    fi
done

if [ ${#MISSING_DEPS[@]} -gt 0 ]; then
    log_error "Dependências ausentes: ${MISSING_DEPS[@]}"
    log_warn "Por favor, instale os pacotes necessários:"
    log_warn "  - php, mysql-server/mariadb-server, unbound, sudo"
    exit 1
fi

# ================================================================
# 3. CRIAR ESTRUTURA DE DIRETÓRIOS
# ================================================================

log_info "Criando estrutura de diretórios necessária..."

dirs_to_create=(
    "$DASHBOARD_DIR/data"
    "$DASHBOARD_DIR/data/tmp"
    "$DASHBOARD_DIR/src/data"
    "$DASHBOARD_DIR/src/data/tmp"
    "/etc/unbound/includes"
)

for dir in "${dirs_to_create[@]}"; do
    if [ ! -d "$dir" ]; then
        sudo mkdir -p "$dir"
        log_ok "Criado: $dir"
    else
        log_ok "Existe: $dir"
    fi
done

# ================================================================
# 4. CONFIGURAR PERMISSÕES
# ================================================================

log_info "Configurando permissões..."

# Permissões do dashboard para www-data
sudo chown -R www-data:www-data "$DASHBOARD_DIR/data"
sudo chmod 775 "$DASHBOARD_DIR/data"
sudo chmod 775 "$DASHBOARD_DIR/data/tmp"
log_ok "Permissões configuradas para www-data"

# Permissões do unbound
sudo chown -R unbound:unbound /etc/unbound
sudo chmod 755 /etc/unbound
sudo chmod 755 /etc/unbound/includes
log_ok "Permissões configuradas para unbound"

# Diretório de dados do unbound
sudo mkdir -p /var/lib/unbound
sudo chown unbound:unbound /var/lib/unbound
sudo chmod 755 /var/lib/unbound
log_ok "Diretório /var/lib/unbound criado"

# ================================================================
# 5. CRIAR ARQUIVOS DE CONFIGURAÇÃO AUSENTES
# ================================================================

log_info "Verificando e criando arquivos de configuração..."

config_files=(
    "/etc/unbound/includes/interfaces.conf"
    "/etc/unbound/includes/general.conf"
    "/etc/unbound/includes/optimization.conf"
    "/etc/unbound/includes/performance.conf"
    "/etc/unbound/includes/security.conf"
    "/etc/unbound/includes/forwarders.conf"
    "/etc/unbound/includes/local_records.conf"
    "/etc/unbound/includes/blocked_domains.conf"
)

for file in "${config_files[@]}"; do
    if [ ! -f "$file" ]; then
        sudo touch "$file"
        echo "# Configuration: $(basename "$file")" | sudo tee "$file" > /dev/null
        sudo chown unbound:unbound "$file"
        sudo chmod 644 "$file"
        log_ok "Criado: $file"
    else
        log_ok "Existe: $file"
    fi
done

# ================================================================
# 6. GERAR CERTIFICADOS TLS PARA UNBOUND CONTROL
# ================================================================

log_info "Verificando certificados TLS para unbound-control..."

if [ ! -f "/etc/unbound/unbound_control.pem" ] || [ ! -f "/etc/unbound/unbound_control.key" ]; then
    log_info "Gerando certificados TLS..."
    sudo unbound-control-setup -d /etc/unbound
    sudo chown unbound:unbound /etc/unbound/unbound_*.pem /etc/unbound/unbound_*.key
    sudo chmod 644 /etc/unbound/unbound_*.pem /etc/unbound/unbound_*.key
    log_ok "Certificados TLS gerados"
else
    log_ok "Certificados TLS já existem"
fi

# ================================================================
# 7. VALIDAR CONFIGURAÇÃO DO UNBOUND
# ================================================================

log_info "Validando configuração do Unbound..."

if unbound-checkconf /etc/unbound/unbound.conf > /dev/null 2>&1; then
    log_ok "✓ Sincronização OK | Arquivo unbound.conf é válido"
else
    log_warn "Servidor Unbound pode ter erros de sintaxe"
    log_warn "Execute: unbound-checkconf /etc/unbound/unbound.conf"
fi

# ================================================================
# 8. REINICIAR SERVIÇOS
# ================================================================

log_info "Reiniciando serviços..."

# Restart Unbound
if systemctl is-enabled unbound > /dev/null 2>&1; then
    sudo systemctl restart unbound
    sleep 2
    
    if systemctl is-active --quiet unbound; then
        log_ok "✓ Serviço Unbound reiniciado com sucesso"
    else
        log_warn "⚠ Serviço Unbound pode não estar rodando"
        log_warn "Execute: sudo systemctl status unbound"
    fi
else
    log_warn "⚠ Serviço Unbound não está habilitado no boot"
    log_warn "Execute: sudo systemctl enable unbound"
fi

# Restart Apache (se disponível)
if command -v "apache2ctl" &> /dev/null; then
    if sudo apache2ctl configtest > /dev/null 2>&1; then
        sudo systemctl restart apache2
        log_ok "✓ Apache reiniciado"
    else
        log_warn "⚠ Erro de configuração do Apache"
    fi
fi

# ================================================================
# 9. CRIAR ARQUIVO DE LOCK
# ================================================================

if [ ! -f "$DASHBOARD_DIR/data/.installed" ]; then
    sudo touch "$DASHBOARD_DIR/data/.installed"
    sudo chown www-data:www-data "$DASHBOARD_DIR/data/.installed"
    log_ok "Marcado como instalado"
fi

# ================================================================
# 10. RELATÓRIO FINAL
# ================================================================

echo ""
echo "╔════════════════════════════════════════════════════════╗"
echo "║              INICIALIZAÇÃO COMPLETA                   ║"
echo "╚════════════════════════════════════════════════════════╝"
echo ""

# Status geral
STATUS_OK=true

# Verificar Unbound
if systemctl is-active --quiet unbound; then
    echo -e "  ${GREEN}✓${NC} Serviço Unbound: ${GREEN}ATIVO${NC}"
else
    echo -e "  ${RED}✗${NC} Serviço Unbound: ${RED}INATIVO${NC}"
    STATUS_OK=false
fi

# Verificar Database
if php -r "require 'src/Database.php';" 2>/dev/null; then
    echo -e "  ${GREEN}✓${NC} Classe Database: ${GREEN}VÁLIDA${NC}"
else
    echo -e "  ${YELLOW}⚠${NC} Classe Database: ${YELLOW}AVISO${NC}"
fi

# Verificar arquivo instalado
if [ -f "$DASHBOARD_DIR/data/.installed" ]; then
    echo -e "  ${GREEN}✓${NC} Marcador de instalação: ${GREEN}OK${NC}"
else
    echo -e "  ${YELLOW}⚠${NC} Marcador de instalação: ${YELLOW}AUSENTE${NC}"
fi

echo ""
echo "Próximos passos:"
echo "  1. Acesse o dashboard: https://seu-dominio/login.php"
echo "  2. Configure as credenciais de admin na primeira execução"
echo "  3. Verifique a saúde do sistema em Saúde & Auditoria"
echo ""

if [ "$STATUS_OK" = true ]; then
    log_ok "Sistema inicializado com sucesso!"
    exit 0
else
    log_warn "Revisar os avisos acima"
    exit 1
fi
