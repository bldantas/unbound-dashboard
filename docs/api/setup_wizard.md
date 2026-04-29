# API: setup_wizard.php

## Propósito

Realiza verificações de ambiente durante a instalação/primeira configuração da aplicação.

## Responsabilidades

- validar a presença de binários necessários como `unbound`
- checar se serviços estão instalados e ativos
- obter versões e status de componentes de sistema
- listar crons e permissões necessárias para o funcionamento do painel
