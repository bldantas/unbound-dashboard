# Database

## Propósito

Centraliza a conexão PDO com a base de dados da aplicação.

## Responsabilidades

- estabelecer conexão com MySQL/MariaDB
- configurar credenciais a partir de variáveis de ambiente ou arquivo de configuração
- fornecer instância singleton para consumo por outros componentes

## Uso típico

Classes como `AlertManager`, `Auth`, `AppMetricsManager` e outros acessam `Database::getInstance()` para executar consultas.

## Observações

A implementação deve evitar múltiplas conexões desnecessárias e garantir que as credenciais sejam carregadas em um único ponto.
