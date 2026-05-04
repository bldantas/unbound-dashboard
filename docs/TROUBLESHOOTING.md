# Guia de Solução de Problemas — Unbound Dashboard

Este guia oferece soluções para os problemas mais comuns na stack atual (PHP + FastAPI/DuckDB/Redis). Para o instalador, veja [MANUAL_INSTALACAO.md](../MANUAL_INSTALACAO.md).

> **Nota:** o sistema foi migrado para DuckDB em 2026-05-04. Problemas legados de MariaDB/MySQL não se aplicam mais — não restaure scripts antigos como `setup_database.sql` ou `fix-mariadb.sh` (já removidos).

## 📋 Problemas Cobertos

1. [api_service não sobe](#1-api_service-não-sobe)
2. [`/api/v1/healthz` retorna 502 via Apache](#2-apiv1healthz-retorna-502-via-apache)
3. [Páginas PHP travam no loader (loading screen infinito)](#3-páginas-php-travam-no-loader)
4. [Arquivo de log do Unbound não encontrado](#4-arquivo-de-log-do-unbound-não-encontrado)
5. [`blocked_domains.conf` ausente em `/etc/unbound/includes/`](#5-blocked_domainsconf-ausente)
6. [Auto-Reparo não reinicia o Unbound](#6-auto-reparo-não-reinicia-o-unbound)
7. [Live Sniffer não inicia](#7-live-sniffer-não-inicia)
8. [Resetar senha do admin](#8-resetar-senha-do-admin)

---

## 1. `api_service` não sobe

### Sintoma

```bash
$ sudo systemctl status unbound-dashboard-api
   Active: failed (Result: exit-code)
```

### Diagnóstico

```bash
sudo journalctl -u unbound-dashboard-api -n 50 --no-pager
```

### Causas comuns e correções

**a) `JWT_SECRET` ainda no placeholder `CHANGE_ME...`**

O `pydantic-settings` falha no startup com mensagem clara. Gere um secret real:

```bash
sudo sed -i "s|^JWT_SECRET=.*|JWT_SECRET=$(openssl rand -hex 32)|" /etc/unbound-dashboard/api-v1.env
sudo systemctl restart unbound-dashboard-api
```

**b) Permissão errada em `/etc/unbound-dashboard/api-v1.env`**

Esperado: `640 root:www-data`.

```bash
sudo chown root:www-data /etc/unbound-dashboard/api-v1.env
sudo chmod 640 /etc/unbound-dashboard/api-v1.env
```

**c) `.venv` corrompido ou desatualizado**

```bash
cd /var/www/html/unbound-dashboard/api_service
sudo /usr/local/bin/uv sync --no-dev
sudo systemctl restart unbound-dashboard-api
```

**d) DuckDB inacessível**

```bash
sudo ls -l /var/lib/unbound-dashboard/unbound_dash.duckdb
# Esperado: owner www-data, dir 750.
sudo chown -R www-data:www-data /var/lib/unbound-dashboard
sudo chmod 750 /var/lib/unbound-dashboard
```

---

## 2. `/api/v1/healthz` retorna 502 via Apache

### Diagnóstico

```bash
curl -i http://127.0.0.1:8001/api/v1/healthz   # direto no FastAPI (esperado: 200)
curl -i http://localhost/api/v1/healthz         # via Apache (esperado: 200; 502 = problema no proxy)
```

### Soluções

**a) FastAPI não está escutando em `:8001`**

```bash
ss -tlnp | grep 8001 || sudo systemctl restart unbound-dashboard-api
```

**b) Módulos Apache desabilitados**

```bash
sudo a2enmod proxy proxy_http proxy_wstunnel headers
sudo a2enconf unbound-dashboard-api
sudo apache2ctl configtest && sudo systemctl reload apache2
```

---

## 3. Páginas PHP travam no loader

### Sintoma

A página fica presa em "Carregando..." indefinidamente.

### Causa

Algum endpoint PHP/api ainda chama `Database::getInstance()` em ponto onde o erro é fatal (por exemplo, antes de um `try/catch`). Como o `Database.php` agora é stub que lança `PDOException`, qualquer caller protegido por `try/catch` degrada graciosamente — mas chamadas incondicionais matam o script e a página fica órfã.

### Solução

Migre o caller para `App\ApiClient` chamando o endpoint FastAPI equivalente. Verifique o log:

```bash
sudo tail -f /var/log/apache2/error.log
```

Mensagens "MariaDB descontinuado" indicam pontos a migrar.

---

## 4. Arquivo de log do Unbound não encontrado

### Sintoma

```
tail: cannot open '/var/log/unbound.log' for reading: No such file or directory
```

### Solução

```bash
sudo /usr/local/bin/setup-unbound-logs.sh
```

Ou manualmente:

```bash
sudo touch /var/log/unbound.log
sudo chown unbound:unbound /var/log/unbound.log
sudo chmod 640 /var/log/unbound.log
sudo systemctl restart unbound
```

---

## 5. `blocked_domains.conf` ausente

### Sintoma

```
cp: cannot create regular file '/etc/unbound/includes/blocked_domains.conf': No such file or directory
```

### Solução

Use o auto-reparo (cria os arquivos automaticamente):

```bash
sudo /usr/local/bin/unbound-health-fix.sh
```

Ou manualmente:

```bash
sudo mkdir -p /etc/unbound/includes
sudo chown unbound:unbound /etc/unbound/includes
sudo chmod 755 /etc/unbound/includes
for c in interfaces general optimization performance security forwarders local_records blocked_domains; do
    sudo touch "/etc/unbound/includes/$c.conf"
    sudo chown unbound:unbound "/etc/unbound/includes/$c.conf"
    sudo chmod 644 "/etc/unbound/includes/$c.conf"
done
sudo unbound-checkconf /etc/unbound/unbound.conf
```

---

## 6. Auto-Reparo não reinicia o Unbound

### Sintoma

O botão "Executar Auto-Reparo" mostra sucesso, mas o Unbound continua parado.

### Diagnóstico

```bash
sudo journalctl -u unbound -n 30 --no-pager
sudo unbound-checkconf /etc/unbound/unbound.conf
```

### Solução

Rode o auto-reparo direto e verifique:

```bash
sudo /usr/local/bin/unbound-health-fix.sh
sudo systemctl status unbound
```

Se ainda não iniciar, há erro de config no `unbound.conf` — o `unbound-checkconf` aponta a linha problemática.

---

## 7. Live Sniffer não inicia

### Sintoma

A aba "Live Sniffer" em `/logs.php` fica vazia.

### Solução

O sniffer lê de `journalctl -u unbound` ou `/var/log/unbound.log` (não usa `tcpdump`). Se nada aparece:

```bash
# Verifique se o Unbound está logando queries em verbose
sudo grep -E "verbosity|log-queries" /etc/unbound/unbound.conf
# Esperado: verbosity > 0 e log-queries: yes
```

Para habilitar logs detalhados (apenas em diagnóstico, gera muito I/O):

```
# em /etc/unbound/unbound.conf
verbosity: 2
log-queries: yes
log-replies: yes
```

```bash
sudo systemctl reload unbound
```

---

## 8. Resetar senha do admin

A página `/recover.php` envia link de recuperação por email. Se o servidor SMTP não está configurado, use o CLI:

```bash
cd /var/www/html/unbound-dashboard/api_service
ADMIN_USERNAME=admin ADMIN_PASSWORD='novaSenha' \
sudo -u www-data \
    env "PYTHONPATH=$(pwd)" $(grep -v '^#' /etc/unbound-dashboard/api-v1.env | xargs) \
    .venv/bin/python tools/create_admin.py
```

> O `create_admin.py` é idempotente: se o usuário já existe, ele **não altera a senha** — só pula. Para resetar de fato, é preciso editar a tabela `users` do DuckDB diretamente ou implementar um endpoint admin (TODO).

---

## 🆘 Coleta de Logs para Suporte

Quando abrir um issue, anexe:

```bash
# Versão e estado dos serviços
cat /var/www/html/unbound-dashboard/VERSION
sudo systemctl status unbound-dashboard-api unbound apache2 redis-server | head -40

# Últimos logs
sudo journalctl -u unbound-dashboard-api -n 100 --no-pager > /tmp/api.log
sudo tail -200 /var/log/apache2/error.log > /tmp/apache.log
sudo journalctl -u unbound -n 100 --no-pager > /tmp/unbound.log

# Smoke teste
curl -sf http://127.0.0.1:8001/api/v1/healthz
curl -sk -o /dev/null -w "%{http_code}\n" -H "Host: dashboard.local" "https://localhost/login.php"
```

---

**Atualizado:** 2026-05-04 (v2.2.x)
