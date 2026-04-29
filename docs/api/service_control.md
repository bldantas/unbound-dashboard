# API: service_control.php

## Propósito

Gerencia ações de serviço para o daemon `unbound` e mantém operações de administração do painel.

## Responsabilidades

- iniciar, parar ou reiniciar o serviço `unbound`
- validar ações permitidas
- usar `\App\ShellHelper::exec()` para chamar `systemctl`
- retornar JSON com status de sucesso e se o serviço está rodando
