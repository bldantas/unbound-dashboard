# AlertManager

## Propósito

Gerencia a geração e resolução de alertas do sistema.

## Responsabilidades

- verificar métricas de CPU, memória, disco e rede
- monitorar falhas de login SSH
- detectar indisponibilidade do banco de dados e servidor web
- checar se o serviço Unbound está em execução
- persistir alertas no banco e resolver alertas antigos quando a condição normaliza

## Uso típico

Invocado em páginas de monitoramento como `alerts.php` e em rotinas de auditoria interna do painel.
