> ⚠️ **DEPRECATED** — este arquivo descreve código pré-modernização v2.2 (2026-05-04) ou anterior. A maior parte do que está aqui foi **removida, refatorada ou substituída** durante a migração MariaDB → DuckDB e a evolução até v2.103.x.
>
> **Arquitetura atual:** [SISTEMA.md](../../SISTEMA.md).
> **Backend Python:** `api_service/app/{routers,services,workers}/`.
>
> Mantido por histórico. Se algo aqui for útil, considere abrir PR pra remover ou atualizar.

---

# API: service_control.php

## Propósito

Gerencia ações de serviço para o daemon `unbound` e mantém operações de administração do painel.

## Responsabilidades

- iniciar, parar ou reiniciar o serviço `unbound`
- validar ações permitidas
- usar `\App\ShellHelper::exec()` para chamar `systemctl`
- retornar JSON com status de sucesso e se o serviço está rodando
