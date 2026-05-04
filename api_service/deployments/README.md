# Deployments — Unbound Dashboard API (v1 modernization)

Arquivos de staging para deploy do FastAPI service no host. **Nada aqui é instalado automaticamente** — copiar manualmente para os paths de sistema após revisão.

## Conteúdo

| Arquivo | Destino sugerido | Permissões |
|---|---|---|
| `systemd/unbound-dashboard-api.service` | `/etc/systemd/system/unbound-dashboard-api.service` | 644 root:root |
| `apache/unbound-dashboard-api.conf` | `/etc/apache2/conf-available/unbound-dashboard-api.conf` | 644 root:root |
| `api-v1.env.example` | `/etc/unbound-dashboard/api-v1.env` (renomear, ajustar valores) | 640 root:www-data |

## Sequência de instalação

### 1. Env file (obrigatório antes de subir o serviço)

```bash
sudo install -d -m 755 -o root -g root /etc/unbound-dashboard
sudo cp /var/www/html/unbound-dashboard/api_service/deployments/api-v1.env.example \
        /etc/unbound-dashboard/api-v1.env

# Gerar JWT_SECRET real e substituir o placeholder
JWT=$(openssl rand -hex 32)
sudo sed -i "s/CHANGE_ME_RUN_openssl_rand_hex_32/$JWT/" /etc/unbound-dashboard/api-v1.env

sudo chown root:www-data /etc/unbound-dashboard/api-v1.env
sudo chmod 640 /etc/unbound-dashboard/api-v1.env
```

> **Nota:** Existe um `/etc/unbound-dashboard/env` separado, criado pelo install do v2 em `/opt/unbound-dashboard/`. Mantemos arquivos distintos (`env` para v2, `api-v1.env` para a modernização v1) para não conflitar.

### 2. Diretório do DuckDB

```bash
sudo install -d -m 750 -o www-data -g www-data /var/lib/unbound-dashboard
```

(Talvez já exista, criado pelo install do v2 — verificar owner antes de sobrescrever.)

### 3. systemd unit

```bash
sudo cp /var/www/html/unbound-dashboard/api_service/deployments/systemd/unbound-dashboard-api.service \
        /etc/systemd/system/
sudo systemctl daemon-reload

# Boot inicial em foreground para verificar logs em tempo real
sudo systemctl start unbound-dashboard-api
sudo journalctl -u unbound-dashboard-api -f --no-pager

# Em outra aba: smoke test
curl -s http://127.0.0.1:8001/api/v1/healthz

# Se OK, habilitar pra subir no boot
sudo systemctl enable unbound-dashboard-api
```

### 4. Apache mod_proxy

```bash
# Habilitar módulos (idempotente)
sudo a2enmod proxy proxy_http proxy_wstunnel headers

# Habilitar a config
sudo cp /var/www/html/unbound-dashboard/api_service/deployments/apache/unbound-dashboard-api.conf \
        /etc/apache2/conf-available/
sudo a2enconf unbound-dashboard-api

# Validar antes de reload
sudo apache2ctl configtest

# Aplicar
sudo systemctl reload apache2

# Smoke test via Apache (deve retornar mesmo JSON do passo 3)
curl -s http://localhost/api/v1/healthz
```

## Rollback

```bash
sudo a2disconf unbound-dashboard-api
sudo systemctl reload apache2
sudo systemctl stop unbound-dashboard-api
sudo systemctl disable unbound-dashboard-api
sudo rm /etc/systemd/system/unbound-dashboard-api.service
sudo systemctl daemon-reload
```

O frontend PHP em `/var/www/html/unbound-dashboard/` continua funcionando normalmente — sem dependência do FastAPI.

## Verificação pós-deploy

```bash
# Healthcheck via Apache (porta pública)
curl -s http://localhost/api/v1/healthz | jq

# Healthcheck direto no Uvicorn (porta interna)
curl -s http://127.0.0.1:8001/api/v1/healthz | jq

# Confirmar que arquivos do api_service/ NÃO são servidos pelo Apache
curl -s -o /dev/null -w "%{http_code}\n" http://localhost/api_service/pyproject.toml  # esperado: 404

# Logs estruturados
sudo journalctl -u unbound-dashboard-api -n 50 --no-pager

# Status do serviço
sudo systemctl status unbound-dashboard-api
```
