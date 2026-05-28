> ⚠️ **DEPRECATED** — este arquivo descreve código pré-modernização v2.2 (2026-05-04) ou anterior. A maior parte do que está aqui foi **removida, refatorada ou substituída** durante a migração MariaDB → DuckDB e a evolução até v2.103.x.
>
> **Arquitetura atual:** [SISTEMA.md](../../SISTEMA.md).
> **Backend Python:** `api_service/app/{routers,services,workers}/`.
>
> Mantido por histórico. Se algo aqui for útil, considere abrir PR pra remover ou atualizar.

---

# API: blocklist_search.php

## Propósito

Fornece pesquisa na lista de domínios bloqueados usada pelo painel.

## Responsabilidades

- receber parâmetros de busca
- consultar a blacklist ou os índices de domínio
- retornar resultados em JSON para a interface de administração
