# BlocklistManager

## Propósito

Gerencia a lista de domínios bloqueados e a sua integração com o Unbound.

## Responsabilidades

- importar e sincronizar listas de bloqueio
- atualizar arquivos de configuração usados pelo Unbound
- manter o painel informado sobre o estado da blacklist

## Uso típico

Usado por rotinas de atualização de blacklist e por componentes que precisam construir a configuração final do Unbound.
