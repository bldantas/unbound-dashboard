> ⚠️ **DEPRECATED** — este arquivo descreve código pré-modernização v2.2 (2026-05-04) ou anterior. A maior parte do que está aqui foi **removida, refatorada ou substituída** durante a migração MariaDB → DuckDB e a evolução até v2.103.x.
>
> **Arquitetura atual:** [SISTEMA.md](../../SISTEMA.md).
> **Backend Python:** `api_service/app/{routers,services,workers}/`.
>
> Mantido por histórico. Se algo aqui for útil, considere abrir PR pra remover ou atualizar.

---

# API: fix_health.php

## Propósito

Executa o script de auto-reparo `unbound-health-fix.sh` a partir do painel.

## Responsabilidades

- verificar se o usuário está autenticado e é administrador
- chamar `\App\ShellHelper::exec()` para executar o script com `sudo`
- retornar JSON com `success`, `message` e `log`

## Observações

O script deve estar no caminho `/usr/local/bin/unbound-health-fix.sh` e o `sudoers` deve permitir sua execução sem senha para o usuário do servidor web.
