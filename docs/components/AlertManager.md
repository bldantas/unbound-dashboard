> ⚠️ **DEPRECATED** — este arquivo descreve código pré-modernização v2.2 (2026-05-04) ou anterior. A maior parte do que está aqui foi **removida, refatorada ou substituída** durante a migração MariaDB → DuckDB e a evolução até v2.103.x.
>
> **Arquitetura atual:** [SISTEMA.md](../../SISTEMA.md).
> **Backend Python:** `api_service/app/{routers,services,workers}/`.
>
> Mantido por histórico. Se algo aqui for útil, considere abrir PR pra remover ou atualizar.

---

# AlertManager

## Propósito

Gerencia a geração e resolução de alertas do sistema.

## Responsabilidades

- verificar métricas de CPU, memória, disco e rede
- monitorar falhas de login SSH
- detectar indisponibilidade do banco de dados e servidor web
- checar se o serviço Unbound está em execução
- persistir alertas no banco e resolver alertas antigos quando a condição normaliza

## Uso típico

Invocado em páginas de monitoramento como `alerts.php` e em rotinas de auditoria interna do painel.
