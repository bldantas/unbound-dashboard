> ⚠️ **DEPRECATED** — este arquivo descreve código pré-modernização v2.2 (2026-05-04) ou anterior. A maior parte do que está aqui foi **removida, refatorada ou substituída** durante a migração MariaDB → DuckDB e a evolução até v2.103.x.
>
> **Arquitetura atual:** [SISTEMA.md](../../SISTEMA.md).
> **Backend Python:** `api_service/app/{routers,services,workers}/`.
>
> Mantido por histórico. Se algo aqui for útil, considere abrir PR pra remover ou atualizar.

---

# SecurityMonitor

## Propósito

Monitora eventos de segurança relevantes e exposições do sistema.

## Responsabilidades

- contar falhas de login SSH ou de sistema
- monitorar portas de escuta abertas
- detectar atualizações de pacotes pendentes ou outras variações de segurança

## Uso típico

Usado no painel de alertas para sinalizar possíveis intrusões e riscos de exposição de serviços.
