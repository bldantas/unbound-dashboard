> ⚠️ **DEPRECATED** — este arquivo descreve código pré-modernização v2.2 (2026-05-04) ou anterior. A maior parte do que está aqui foi **removida, refatorada ou substituída** durante a migração MariaDB → DuckDB e a evolução até v2.103.x.
>
> **Arquitetura atual:** [SISTEMA.md](../../SISTEMA.md).
> **Backend Python:** `api_service/app/{routers,services,workers}/`.
>
> Mantido por histórico. Se algo aqui for útil, considere abrir PR pra remover ou atualizar.

---

# UnboundConfigManager

## Propósito

Gerencia a montagem, validação e implantação das configurações do Unbound.

## Responsabilidades

- construir configurações a partir de blocos modulares
- copiar arquivos temporários para os locais finais
- validar a sintaxe usando `unbound-checkconf`
- reiniciar o serviço `unbound` quando necessário

## Uso típico

Usado pelo painel de configuração e por rotinas de deploy para aplicar mudanças de forma segura.
