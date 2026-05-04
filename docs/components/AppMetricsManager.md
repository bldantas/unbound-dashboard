# AppMetricsManager

## Propósito

Fornece métricas de serviços a nível de aplicação para o painel `alerts.php`.

## Estado atual (v2.2.x)

Após o tear-down do MariaDB (2026-05-04), a classe foi simplificada:

| Método | Comportamento atual |
|---|---|
| `getMariaDBStats()` | Retorna **stub fixo** `{status: 'offline', connections: 0, queries: 0, slow: 0}`. Mantido para compat com o frontend de `alerts.php` enquanto o widget não é substituído. |
| `getWebServerStatus()` | Detecta via `systemctl is-active` qual webserver está rodando: `nginx_active`, `apache2_active`, `httpd_active` ou `offline`. |

O constructor não depende mais de `Database::getInstance()` — antes, instanciar a classe matava o endpoint `api/alerts_metrics.php` com `die()` em cascata.

## Substituição planejada

O widget "Banco de Dados" de `alerts.php` deve ser refatorado para mostrar status do `unbound-dashboard-api` + tamanho/saúde do DuckDB. Tarefa em aberto.

## Uso típico

Chamado por `api/alerts_metrics.php` (que combina ServerMonitor + SecurityMonitor + AppMetricsManager num único payload de métricas), consumido pelo JS de `alerts.php`.
