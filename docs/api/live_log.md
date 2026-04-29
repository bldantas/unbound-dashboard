# API: live_log.php

## Propósito

Fornece as últimas linhas dos logs do Unbound para visualização em tempo real.

## Responsabilidades

- validar o usuário e a autorização
- ler as últimas 300 linhas de `/var/log/unbound.log`
- se o arquivo não estiver disponível, usar `journalctl` para obter logs do `unbound`
- retornar JSON com as entradas de log encontradas
