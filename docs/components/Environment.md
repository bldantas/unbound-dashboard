> ⚠️ **DEPRECATED** — este arquivo descreve código pré-modernização v2.2 (2026-05-04) ou anterior. A maior parte do que está aqui foi **removida, refatorada ou substituída** durante a migração MariaDB → DuckDB e a evolução até v2.103.x.
>
> **Arquitetura atual:** [SISTEMA.md](../../SISTEMA.md).
> **Backend Python:** `api_service/app/{routers,services,workers}/`.
>
> Mantido por histórico. Se algo aqui for útil, considere abrir PR pra remover ou atualizar.

---

# Environment

## Propósito

Gerencia a leitura de variáveis de ambiente e configurações do sistema.

## Responsabilidades

- carregar valores de `getenv()` e `$_ENV`
- suportar fallback para arquivo `.env`
- garantir que a aplicação use configurações externas para banco de dados, caminhos e variáveis sensíveis

## Uso típico

Usada por componentes que precisam de configurações de ambiente, incluindo `Database` e outros módulos de infraestrutura.
