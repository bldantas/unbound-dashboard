# Database

> ⚠️ **DEPRECATED desde 2026-05-04 (v2.2.0).** MariaDB foi removido do sistema.

`src/Database.php` é mantido como **stub** que sempre lança `PDOException` com a mensagem `"MariaDB descontinuado — use App\\ApiClient para FastAPI/DuckDB"`.

## Por que stub e não exclusão?

Vários callers legados (`AppMetricsManager`, `SystemCheckManager`, `health.php`, `api/threats_data.php`) chamam `Database::getInstance()` dentro de `try/catch (\Throwable)`. Mantendo a classe como stub que lança em vez de `die()`, esses callers degradam graciosamente em vez de matar o script — críticas pra páginas como `alerts.php` e `health.php` que renderizam mesmo sem o card "MariaDB Stats".

## Substituto

Use `App\ApiClient` (em `src/ApiClient.php`) para acessar dados via FastAPI/DuckDB:

```php
$resp = \App\ApiClient::get('/api/v1/exports/settings', $_SESSION['api_jwt']);
if ($resp['ok']) { /* $resp['data'] */ }
```

Endpoints disponíveis sob `/api/v1/`: `auth`, `alerts`, `blocklist`, `exports`, `health`, `history`, `stats`, `threats`, `unbound`, `users`.

## Quando remover por completo

Quando todos os callers tiverem sido migrados — verificar com:

```bash
grep -rn "Database::getInstance\\|new \\\\App\\\\Database" src/ api/ *.php
```

No estado atual, ainda há callers protegidos por `try/catch` que dependem do stub para receber a exception em vez de `die()`.

## Histórico

A classe original (até v2.1.x) provia conexão PDO com MariaDB usando credenciais de `Environment::get('DB_*')`. O `die()` no `PDOException` foi substituído por `throw` em 2026-05-04 para evitar matar scripts cujo loader já tinha sido flushed (caso típico que travava `history.php`).
