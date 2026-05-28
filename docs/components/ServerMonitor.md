> ⚠️ **DEPRECATED** — este arquivo descreve código pré-modernização v2.2 (2026-05-04) ou anterior. A maior parte do que está aqui foi **removida, refatorada ou substituída** durante a migração MariaDB → DuckDB e a evolução até v2.103.x.
>
> **Arquitetura atual:** [SISTEMA.md](../../SISTEMA.md).
> **Backend Python:** `api_service/app/{routers,services,workers}/`.
>
> Mantido por histórico. Se algo aqui for útil, considere abrir PR pra remover ou atualizar.

---

# ServerMonitor

## Propósito

Coleta métricas de hardware e uso de recursos do servidor.

## Responsabilidades

- obter uptime e uso de CPU
- calcular uso de memória e swap
- medir uso de disco em volumes
- coletar erros e drops de rede por interface

## Uso típico

Usado em dashboards de saúde para apresentar indicadores de hardware em tempo real.
