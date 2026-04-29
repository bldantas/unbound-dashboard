# Guia de Solução de Problemas - Unbound Dashboard

Após instalar o Unbound Dashboard em uma nova máquina, alguns problemas podem ocorrer. Este guia oferece soluções para os problemas mais comuns.

## 📋 Problemas Cobertos

1. [Problemas com Banco de Dados](#1-problemas-com-banco-de-dados)
2. [Arquivo de Log não Encontrado](#2-arquivo-de-log-não-encontrado)
3. [Arquivo blocked_domains.conf Ausente](#3-arquivo-blocked_domainsconf-ausente)
4. [Auto-Reparo não Reinicia o Serviço](#4-auto-reparo-não-reinicia-o-serviço-unbound)
5. [Live Sniffer não Inicia](#5-live-sniffer-não-inicia)

---

## 1. Problemas com Banco de Dados

### Sintoma
Erro ao conectar: `SQLSTATE[HY000] [1698]` ou `Access denied for user 'unbounddb'@'localhost'`

### Causa
O usuário MySQL foi criado com o plugin de autenticação errado (auth_socket em vez de mysql_native_password).

### Solução Rápida

#### Opção A: Executar o Script Automaticamente (RECOMENDADO)

```bash
# Como root
mysql -u root -p < /var/www/html/unbound-dashboard/scripts/setup_database.sql
```

Será solicitada a senha do root do MySQL. O script irá:
- Criar o banco de dados `unbound_dash`
- Criar o usuário `unbounddb` com autenticação nativa
- Conceder todos os privilégios necessários
- Crear todas as tabelas

#### Opção B: Configuração Manual via Wizard

Se o banco já foi criado incorretamente:

```bash
# Como root, execute:
mysql -u root -p

# Dentro do MySQL:
DROP USER IF EXISTS 'unbounddb'@'localhost';
DROP USER IF EXISTS 'unbounddb'@'127.0.0.1';
DROP USER IF EXISTS 'unbounddb'@'%';

CREATE USER 'unbounddb'@'localhost' IDENTIFIED WITH mysql_native_password BY 'unbounddash';
CREATE USER 'unbounddb'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY 'unbounddash';
CREATE USER 'unbounddb'@'%' IDENTIFIED WITH mysql_native_password BY 'unbounddash';

GRANT ALL PRIVILEGES ON `unbound_dash`.* TO 'unbounddb'@'localhost' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON `unbound_dash`.* TO 'unbounddb'@'127.0.0.1' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON `unbound_dash`.* TO 'unbounddb'@'%' WITH GRANT OPTION;

GRANT PROCESS ON *.* TO 'unbounddb'@'localhost';
GRANT PROCESS ON *.* TO 'unbounddb'@'127.0.0.1';
GRANT PROCESS ON *.* TO 'unbounddb'@'%';

FLUSH PRIVILEGES;
EXIT;
```

Então, reexecute o setup wizard no navegador: `https://seu-dominio/setup.php`

---

## 2. Arquivo de Log não Encontrado

### Sintoma
```
tail: cannot open '/var/log/syslog' for reading: No such file or directory
```

### Causa
Diferentes distribuições Linux usam diferentes caminhos para logs do sistema:
- Debian/Ubuntu: `/var/log/syslog`
- CentOS/RHEL/Fedora: `/var/log/messages`
- Alpine: `/var/log/all.log`
- macOS: `/var/log/system.log`

### Solução
A correção foi aplicada automaticamente no arquivo `logs.php`. O sistema agora:
- Detecta automaticamente o arquivo de log correto
- Tenta vários caminhos comuns
- Exibe um aviso se nenhum arquivo for encontrado

**Para testar:**
```bash
# Acesse a página de logs no navegador:
# https://seu-dominio/logs.php
```

Se ainda houver problemas, verifique o caminho do seu syslog:
```bash
# Encontrar o arquivo de log do sistema
find /var/log -name "syslog" -o -name "messages" -o -name "all.log"
```

---

## 3. Arquivo blocked_domains.conf Ausente

### Sintoma
```
Falha ao salvar lista de bloqueio modular: cp: cannot create regular file '/etc/unbound/includes/blocked_domains.conf': No such file or directory
```

### Causa
O diretório `/etc/unbound/includes/` ou o arquivo `blocked_domains.conf` não foi criado durante a instalação.

### Solução Automática (RECOMENDADO)

Execute o script de inicialização do sistema:

```bash
sudo bash /var/www/html/unbound-dashboard/scripts/init_system.sh
```

Este script irá:
- Criar o diretório `/etc/unbound/includes` se não existir
- Criar todos os arquivos de configuração necessários (interfaces.conf, general.conf, etc.)
- Configurar as permissões corretas
- Reiniciar os serviços

### Solução Manual

Se você não pode executar o script de inicialização:

```bash
# Como root
sudo mkdir -p /etc/unbound/includes
sudo chown unbound:unbound /etc/unbound/includes
sudo chmod 755 /etc/unbound/includes

# Criar os arquivos de configuração
for config in interfaces general optimization performance security forwarders local_records blocked_domains; do
    sudo touch /etc/unbound/includes/${config}.conf
    echo "# Configuration: ${config}.conf" | sudo tee /etc/unbound/includes/${config}.conf > /dev/null
    sudo chown unbound:unbound /etc/unbound/includes/${config}.conf
    sudo chmod 644 /etc/unbound/includes/${config}.conf
done
```

Depois, valide a configuração:
```bash
unbound-checkconf /etc/unbound/unbound.conf
```

---

## 4. Auto-Reparo não Reinicia o Serviço Unbound

### Sintoma
O botão "Executar Auto-Reparo" mostra sucesso, mas o serviço Unbound continua desligado.

### Causa
O script anterior (`unbound-health-fix.sh`) corrigia permissões e certificados, mas não reiniciava o serviço.

### Solução
A correção foi aplicada no arquivo `/usr/local/bin/unbound-health-fix.sh`. O script agora:
- Corrige permissões e certificados
- **Reinicia o serviço Unbound**
- Verifica se o serviço está realmente rodando
- Exibe o status no final

**Para testar manualmente:**
```bash
# Executar o script de auto-reparo
sudo /usr/local/bin/unbound-health-fix.sh

# Verificar status final
sudo systemctl status unbound
```

**Se o serviço ainda não iniciar:**

```bash
# Ver logs de erro
sudo journalctl -u unbound -n 20 --no-pager

# Ou verificar o arquivo de log
sudo tail -50 /var/log/unbound.log

# Validar configuração
unbound-checkconf /etc/unbound/unbound.conf

# Tentar iniciar manualmente
sudo systemctl start unbound
```

---

## 5. Live Sniffer não Inicia

### Sintoma
A seção "Live Sniffer" em Logs está vazia ou mostra erro.

### Causa
O Live Sniffer pode não estar configurado ou o comando `tcpdump` pode estar ausente.

### Solução

#### Passo 1: Instalar Dependências

```bash
# Debian/Ubuntu
sudo apt-get install tcpdump

# CentOS/RHEL
sudo yum install tcpdump

# Alpine
sudo apk add tcpdump
```

#### Passo 2: Configurar Permissões

```bash
# Permitir www-data usar tcpdump sem sudo
sudo setcap cap_net_raw,cap_net_admin=eip /usr/sbin/tcpdump

# Ou adicionar www-data ao grupo de rede
sudo usermod -a -G libvirt-qemu www-data
sudo usermod -a -G packet www-data
```

#### Passo 3: Reiniciar Apache

```bash
sudo systemctl restart apache2
```

---

## 🚀 Inicialização Completa do Sistema

Para resolver **todos os problemas em uma só vez**, execute:

```bash
# Como root ou com sudo sem senha
sudo bash /var/www/html/unbound-dashboard/scripts/init_system.sh
```

Este script completo irá:
- ✅ Verificar dependências (PHP, MySQL, Unbound, etc.)
- ✅ Criar diretórios necessários
- ✅ Configurar permissões corretas
- ✅ Criar arquivos de configuração ausentes
- ✅ Gerar certificados TLS
- ✅ Validar configuração do Unbound
- ✅ Reiniciar serviços
- ✅ Mostrar relatório de status

---

## 📝 Checklist Pós-Instalação

Após resolver os problemas, execute o checklist:

- [ ] Banco de dados conectando sem erros
- [ ] Logs do sistema carregando sem erros
- [ ] Página de Saúde & Auditoria muito verde
- [ ] Auto-Reparo mostra "Sistema ready"
- [ ] Serviço Unbound está ativo em "Serviço Unbound"
- [ ] Dashboard carrega sem erros HTTP 500
- [ ] Pode salvar configurações sem erros de arquivo

---

## 🆘 Suporte Adicional

Se os problemas persistirem:

1. **Verifique os logs**:
   ```bash
   sudo journalctl -u unbound -n 100
   sudo journalctl -u apache2 -n 100
   sudo grep -i error /var/log/apache2/error.log
   ```

2. **Teste conectividade do banco**:
   ```bash
   mysql -u unbounddb -p -D unbound_dash -h 127.0.0.1
   ```

3. **Valide configuração do Unbound**:
   ```bash
   unbound-checkconf -v /etc/unbound/unbound.conf
   ```

4. **Teste o DNS**:
   ```bash
   dig @localhost google.com
   ```

---

**Versão:** 1.0  
**Data:** Abril 2026  
**Dashboard:** Unbound v1.21+
