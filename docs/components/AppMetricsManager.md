> ⚠️ **DEPRECATED** — este arquivo descreve código pré-modernização v2.2 (2026-05-04) ou anterior. A maior parte do que está aqui foi **removida, refatorada ou substituída** durante a migração MariaDB → DuckDB e a evolução até v2.103.x.
>
> **Arquitetura atual:** [SISTEMA.md](../../SISTEMA.md).
> **Backend Python:** `api_service/app/{routers,services,workers}/`.
>
> Mantido por histórico. Se algo aqui for útil, considere abrir PR pra remover ou atualizar.

---

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
