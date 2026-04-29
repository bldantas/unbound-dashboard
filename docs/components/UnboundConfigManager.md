# UnboundConfigManager

## Propósito

Gerencia a montagem, validação e implantação das configurações do Unbound.

## Responsabilidades

- construir configurações a partir de blocos modulares
- copiar arquivos temporários para os locais finais
- validar a sintaxe usando `unbound-checkconf`
- reiniciar o serviço `unbound` quando necessário

## Uso típico

Usado pelo painel de configuração e por rotinas de deploy para aplicar mudanças de forma segura.
