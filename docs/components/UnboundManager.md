> ⚠️ **DEPRECATED** — este arquivo descreve código pré-modernização v2.2 (2026-05-04) ou anterior. A maior parte do que está aqui foi **removida, refatorada ou substituída** durante a migração MariaDB → DuckDB e a evolução até v2.103.x.
>
> **Arquitetura atual:** [SISTEMA.md](../../SISTEMA.md).
> **Backend Python:** `api_service/app/{routers,services,workers}/`.
>
> Mantido por histórico. Se algo aqui for útil, considere abrir PR pra remover ou atualizar.

---

# UnboundManager

## Propósito

Gerencia o serviço Unbound e a obtenção de métricas do daemon do DNS.

## Responsabilidades

- verificar se o serviço `unbound` está rodando
- executar `unbound-control stats_noreset`
- interpretar estado do daemon e métricas específicas do Unbound

## Uso típico

Usado pelo dashboard, pelo `AlertManager` e por operadores que precisam do estado real do Unbound.
