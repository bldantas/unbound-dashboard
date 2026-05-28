> ⚠️ **DEPRECATED** — este arquivo descreve código pré-modernização v2.2 (2026-05-04) ou anterior. A maior parte do que está aqui foi **removida, refatorada ou substituída** durante a migração MariaDB → DuckDB e a evolução até v2.103.x.
>
> **Arquitetura atual:** [SISTEMA.md](../../SISTEMA.md).
> **Backend Python:** `api_service/app/{routers,services,workers}/`.
>
> Mantido por histórico. Se algo aqui for útil, considere abrir PR pra remover ou atualizar.

---

# ShellHelper

## Propósito

Fornece uma interface centralizada para execução segura de comandos de shell a partir do PHP.

## Responsabilidades

- construir comandos com `escapeshellcmd` e `escapeshellarg`
- executar comandos via `exec()`
- capturar saída e código de retorno
- evitar misturas de saída entre execuções
- usar caminho absoluto para `sudo` quando necessário

## Principais métodos

- `buildCommand(string $binary, array $args = [], bool $useSudo = false, bool $captureStderr = true): string`
- `exec(string $binary, array $args = [], array &$output = null, int &$returnVar = null, bool $useSudo = true): string`
- `shell(string $command, array &$output = null, int &$returnVar = null): string`

## Uso típico

Classes de serviço como `AppMetricsManager`, `NetworkManager`, `SystemCheckManager` e `UnboundConfigManager` usam `ShellHelper` para chamar binários do sistema de forma segura.
