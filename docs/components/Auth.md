> ⚠️ **DEPRECATED** — este arquivo descreve código pré-modernização v2.2 (2026-05-04) ou anterior. A maior parte do que está aqui foi **removida, refatorada ou substituída** durante a migração MariaDB → DuckDB e a evolução até v2.103.x.
>
> **Arquitetura atual:** [SISTEMA.md](../../SISTEMA.md).
> **Backend Python:** `api_service/app/{routers,services,workers}/`.
>
> Mantido por histórico. Se algo aqui for útil, considere abrir PR pra remover ou atualizar.

---

# Auth

## Propósito

Controlar a autenticação e autorização de usuários na aplicação.

## Responsabilidades

- iniciar sessão e gerenciar `$_SESSION`
- validar login e senha do usuário
- gerar token CSRF
- verificar status de usuário e privilégios de administrador

## Uso típico

Pages protegidas como `health.php`, `alerts.php`, `diagnostics.php` e APIs como `fix_health.php` chamam `App\Auth::check()` e `App\Auth::isAdmin()`.
