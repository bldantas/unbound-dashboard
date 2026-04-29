# Environment

## Propósito

Gerencia a leitura de variáveis de ambiente e configurações do sistema.

## Responsabilidades

- carregar valores de `getenv()` e `$_ENV`
- suportar fallback para arquivo `.env`
- garantir que a aplicação use configurações externas para banco de dados, caminhos e variáveis sensíveis

## Uso típico

Usada por componentes que precisam de configurações de ambiente, incluindo `Database` e outros módulos de infraestrutura.
