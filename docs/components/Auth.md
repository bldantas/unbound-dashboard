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
