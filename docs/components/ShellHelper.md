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
