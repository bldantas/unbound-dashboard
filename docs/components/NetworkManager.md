> ⚠️ **DEPRECATED** — este arquivo descreve código pré-modernização v2.2 (2026-05-04) ou anterior. A maior parte do que está aqui foi **removida, refatorada ou substituída** durante a migração MariaDB → DuckDB e a evolução até v2.103.x.
>
> **Arquitetura atual:** [SISTEMA.md](../../SISTEMA.md).
> **Backend Python:** `api_service/app/{routers,services,workers}/`.
>
> Mantido por histórico. Se algo aqui for útil, considere abrir PR pra remover ou atualizar.

---

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
