> ⚠️ **DEPRECATED** — este arquivo descreve código pré-modernização v2.2 (2026-05-04) ou anterior. A maior parte do que está aqui foi **removida, refatorada ou substituída** durante a migração MariaDB → DuckDB e a evolução até v2.103.x.
>
> **Arquitetura atual:** [SISTEMA.md](../../SISTEMA.md).
> **Backend Python:** `api_service/app/{routers,services,workers}/`.
>
> Mantido por histórico. Se algo aqui for útil, considere abrir PR pra remover ou atualizar.

---

# DiagnosticsManager

## Propósito

Executa verificações de conectividade e diagnósticos do ambiente de rede.

## Responsabilidades

- executar `ping`, `traceroute`, `whois` e `dig`
- coletar e formatar saídas de diagnóstico
- atualizar root hints via `unbound-anchor` quando necessário
- orquestrar auditoria de conectividade externa

## Uso típico

Usado pelas páginas de diagnóstico e por ferramentas de correção para avaliar a saúde da rede e do DNS.
