# API: export.php

## Propósito

Exporta e importa dados do painel, incluindo logs e configurações.

## Responsabilidades

- gerar tarballs de exportação com arquivos de log e estado
- permitir upload e extração de backups
- validar configurações do Unbound antes de aplicar mudanças
- reiniciar serviços se necessário

## Observações

Usa `\App\ShellHelper::exec()` para executar `tar`, copiar arquivos e validar o ambiente antes de restaurar.
