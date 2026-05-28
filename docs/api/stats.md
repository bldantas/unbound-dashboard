> ⚠️ **DEPRECATED** — este arquivo descreve código pré-modernização v2.2 (2026-05-04) ou anterior. A maior parte do que está aqui foi **removida, refatorada ou substituída** durante a migração MariaDB → DuckDB e a evolução até v2.103.x.
>
> **Arquitetura atual:** [SISTEMA.md](../../SISTEMA.md).
> **Backend Python:** `api_service/app/{routers,services,workers}/`.
>
> Mantido por histórico. Se algo aqui for útil, considere abrir PR pra remover ou atualizar.

---

# API: stats.php

## Propósito

Retorna estatísticas agregadas de uso e atividade do Unbound para o painel.

## Responsabilidades

- coletar métricas de tráfego DNS, consultas e histórico
- fornecer dados em formato JSON para gráficos e dashboards
- suportar parâmetros de filtro quando necessário
