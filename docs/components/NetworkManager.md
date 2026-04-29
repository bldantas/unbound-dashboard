# NetworkManager

## Propósito

Gerencia configurações de rede locais do sistema e interações com o resolv.conf.

## Responsabilidades

- alterar hostname com `hostnamectl`
- gerenciar `resolv.conf` e configurações de DNS
- listar interfaces e endereços IP via `ip addr`
- ajustar timezone com `timedatectl`, validando contra os identificadores válidos do PHP
- normalizar servidores NTP preservando hostnames, IPv4 e IPv6
- aplicar mudanças em arquivos de interface e DNS

## Uso típico

Usado por páginas e APIs que permitem administrar a rede do host onde o painel está instalado.
