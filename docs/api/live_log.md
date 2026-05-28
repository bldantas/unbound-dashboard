> ⚠️ **DEPRECATED** — este arquivo descreve código pré-modernização v2.2 (2026-05-04) ou anterior. A maior parte do que está aqui foi **removida, refatorada ou substituída** durante a migração MariaDB → DuckDB e a evolução até v2.103.x.
>
> **Arquitetura atual:** [SISTEMA.md](../../SISTEMA.md).
> **Backend Python:** `api_service/app/{routers,services,workers}/`.
>
> Mantido por histórico. Se algo aqui for útil, considere abrir PR pra remover ou atualizar.

---

# API: live_log.php

## Propósito

Fornece as últimas linhas dos logs do Unbound para visualização em tempo real.

## Responsabilidades

- validar o usuário e a autorização
- ler as últimas 300 linhas de `/var/log/unbound.log`
- se o arquivo não estiver disponível, usar `journalctl` para obter logs do `unbound`
- retornar JSON com as entradas de log encontradas
