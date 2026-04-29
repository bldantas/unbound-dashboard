# UnboundManager

## Propósito

Gerencia o serviço Unbound e a obtenção de métricas do daemon do DNS.

## Responsabilidades

- verificar se o serviço `unbound` está rodando
- executar `unbound-control stats_noreset`
- interpretar estado do daemon e métricas específicas do Unbound

## Uso típico

Usado pelo dashboard, pelo `AlertManager` e por operadores que precisam do estado real do Unbound.
