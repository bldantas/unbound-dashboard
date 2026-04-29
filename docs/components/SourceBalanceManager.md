# SourceBalanceManager

## Propósito

Gerencia múltiplas instâncias do serviço Unbound e o balanceamento de fontes de dados.

## Responsabilidades

- criar e configurar serviços `unbound*` adicionais
- habilitar/desabilitar e reiniciar instâncias
- aplicar regras de nftables e ajustes de sistema necessários
- copiar arquivos de configuração e recarregar `systemd`

## Uso típico

Utilizado quando o painel precisa gerenciar múltiplas instâncias ou rotas de consulta em paralelo.
