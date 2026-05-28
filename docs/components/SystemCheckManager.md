> ⚠️ **DEPRECATED** — este arquivo descreve código pré-modernização v2.2 (2026-05-04) ou anterior. A maior parte do que está aqui foi **removida, refatorada ou substituída** durante a migração MariaDB → DuckDB e a evolução até v2.103.x.
>
> **Arquitetura atual:** [SISTEMA.md](../../SISTEMA.md).
> **Backend Python:** `api_service/app/{routers,services,workers}/`.
>
> Mantido por histórico. Se algo aqui for útil, considere abrir PR pra remover ou atualizar.

---

# SystemCheckManager

## Propósito

Realiza checagens gerais de sistema e serviço para avaliar a integridade do ambiente.

## Responsabilidades

- checar estado de serviços do sistema
- obter versão do Unbound instalada
- validar sintaxe de configuração do Unbound

## Uso típico

Utilizado em rotinas de auditoria e em páginas que precisam confirmar se o ambiente está íntegro antes de operações críticas.
