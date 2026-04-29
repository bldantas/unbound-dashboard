# Página: reset.php

## Propósito

Permite a redefinição de senha a partir de um token de recuperação válido.

## Responsabilidades

- receber token enviado por e-mail
- validar o token e verificá-lo contra o banco de dados
- aceitar nova senha e atualizar o usuário
- exibir mensagens de sucesso ou falha no processo
