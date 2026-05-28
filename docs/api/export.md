> ⚠️ **DEPRECATED** — este arquivo descreve código pré-modernização v2.2 (2026-05-04) ou anterior. A maior parte do que está aqui foi **removida, refatorada ou substituída** durante a migração MariaDB → DuckDB e a evolução até v2.103.x.
>
> **Arquitetura atual:** [SISTEMA.md](../../SISTEMA.md).
> **Backend Python:** `api_service/app/{routers,services,workers}/`.
>
> Mantido por histórico. Se algo aqui for útil, considere abrir PR pra remover ou atualizar.

---

# API: export.php

## Propósito

Exporta e importa dados do painel, incluindo logs e configurações.

## Responsabilidades

- gerar tarballs de exportação com arquivos de log e estado
- permitir upload e extração de backups
- validar configurações do Unbound antes de aplicar mudanças
- reiniciar serviços se necessário

## Observações

Usa `\App\ShellHelper::exec()` para executar `tar`, copiar arquivos e validar o ambiente antes de restaurar.
