> ⚠️ **DEPRECATED** — este arquivo descreve código pré-modernização v2.2 (2026-05-04) ou anterior. A maior parte do que está aqui foi **removida, refatorada ou substituída** durante a migração MariaDB → DuckDB e a evolução até v2.103.x.
>
> **Arquitetura atual:** [SISTEMA.md](../../SISTEMA.md).
> **Backend Python:** `api_service/app/{routers,services,workers}/`.
>
> Mantido por histórico. Se algo aqui for útil, considere abrir PR pra remover ou atualizar.

---

# BlocklistManager

## Propósito

Gerencia a lista de domínios bloqueados e a sua integração com o Unbound.

## Responsabilidades

- importar e sincronizar listas de bloqueio
- atualizar arquivos de configuração usados pelo Unbound
- manter o painel informado sobre o estado da blacklist

## Uso típico

Usado por rotinas de atualização de blacklist e por componentes que precisam construir a configuração final do Unbound.
