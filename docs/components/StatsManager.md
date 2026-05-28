> ⚠️ **DEPRECATED** — este arquivo descreve código pré-modernização v2.2 (2026-05-04) ou anterior. A maior parte do que está aqui foi **removida, refatorada ou substituída** durante a migração MariaDB → DuckDB e a evolução até v2.103.x.
>
> **Arquitetura atual:** [SISTEMA.md](../../SISTEMA.md).
> **Backend Python:** `api_service/app/{routers,services,workers}/`.
>
> Mantido por histórico. Se algo aqui for útil, considere abrir PR pra remover ou atualizar.

---

# StatsManager

## Propósito

Coleta e organiza estatísticas do aplicativo e do sistema para exibição no painel.

## Responsabilidades

- agregar dados históricos de desempenho
- preparar séries temporais para gráficos e métricas
- armazenar resultados em formatos JSON usados pelo frontend

## Uso típico

Usado por páginas de estatísticas e relatórios que precisam de dados agregados de longo prazo.
