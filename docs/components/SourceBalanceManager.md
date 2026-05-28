> ⚠️ **DEPRECATED** — este arquivo descreve código pré-modernização v2.2 (2026-05-04) ou anterior. A maior parte do que está aqui foi **removida, refatorada ou substituída** durante a migração MariaDB → DuckDB e a evolução até v2.103.x.
>
> **Arquitetura atual:** [SISTEMA.md](../../SISTEMA.md).
> **Backend Python:** `api_service/app/{routers,services,workers}/`.
>
> Mantido por histórico. Se algo aqui for útil, considere abrir PR pra remover ou atualizar.

---

# SourceBalanceManager

## Propósito

Gerencia múltiplas instâncias do serviço Unbound e o balanceamento de fontes de dados.

## Responsabilidades

- criar e configurar serviços `unbound*` adicionais
- habilitar/desabilitar e reiniciar instâncias
- aplicar regras de nftables e ajustes de sistema necessários
- copiar arquivos de configuração e recarregar `systemd`

## Uso típico

Utilizado quando o painel precisa gerenciar múltiplas instâncias ou rotas de consulta em paralelo.
