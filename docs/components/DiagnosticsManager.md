# DiagnosticsManager

## Propósito

Executa verificações de conectividade e diagnósticos do ambiente de rede.

## Responsabilidades

- executar `ping`, `traceroute`, `whois` e `dig`
- coletar e formatar saídas de diagnóstico
- atualizar root hints via `unbound-anchor` quando necessário
- orquestrar auditoria de conectividade externa

## Uso típico

Usado pelas páginas de diagnóstico e por ferramentas de correção para avaliar a saúde da rede e do DNS.
