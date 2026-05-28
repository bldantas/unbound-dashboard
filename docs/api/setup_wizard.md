> ⚠️ **DEPRECATED** — este arquivo descreve código pré-modernização v2.2 (2026-05-04) ou anterior. A maior parte do que está aqui foi **removida, refatorada ou substituída** durante a migração MariaDB → DuckDB e a evolução até v2.103.x.
>
> **Arquitetura atual:** [SISTEMA.md](../../SISTEMA.md).
> **Backend Python:** `api_service/app/{routers,services,workers}/`.
>
> Mantido por histórico. Se algo aqui for útil, considere abrir PR pra remover ou atualizar.

---

# API: setup_wizard.php

## Propósito

Realiza verificações de ambiente durante a instalação/primeira configuração da aplicação.

## Responsabilidades

- validar a presença de binários necessários como `unbound`
- checar se serviços estão instalados e ativos
- obter versões e status de componentes de sistema
- listar crons e permissões necessárias para o funcionamento do painel
