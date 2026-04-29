# AppMetricsManager

## Propósito

Fornece métricas do ambiente de aplicação, incluindo status de serviços como MariaDB e servidor web.

## Responsabilidades

- checar o status do banco MariaDB
- contar conexões ativas
- detectar se `nginx`, `apache2` ou `httpd` estão rodando

## Uso típico

Usado pelos dashboards e pelo `AlertManager` para gerar alertas quando os serviços estão fora do ar.
