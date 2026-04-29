# Teste de Anomalias de Rede

Procedimento para simular e validar a detecção de anomalias no dashboard.

## O que é detectado

| Métrica | Chave Unbound | Descrição |
|---|---|---|
| **Queries Recusadas** | `unwanted.queries` | Queries DNS de IPs fora das ACLs configuradas em `access-control` |
| **Replies Indesejadas** | `unwanted.replies` | Respostas de upstream que não correspondem a nenhuma query pendente (possível cache poisoning/spoofing) |

## Pré-requisitos

- Acesso root ao servidor
- `dig` instalado (`apt install dnsutils`)
- Unbound rodando com `control-enable: yes`

## Simulação de Queries Recusadas (unwanted.queries)

```bash
# 1. Criar IP temporário fora das ACLs (0.0.0.0/0 = refuse)
sudo ip addr add 1.2.3.4/32 dev lo

# 2. Enviar queries de origem não autorizada
for i in $(seq 1 10); do
  dig @10.100.21.222 -b 1.2.3.4 "test${i}.com" +timeout=1 +tries=1 &
done
wait

# 3. Verificar contadores do Unbound
sudo unbound-control -c /etc/unbound/unbound.conf stats_noreset | grep unwanted

# 4. Atualizar cache do dashboard
php /var/www/html/unbound-dashboard/scripts/aggregate_stats.php

# 5. Verificar JSON do cache
grep unwanted /var/www/html/unbound-dashboard/data/latest_stats.json

# 6. Limpar IP temporário
sudo ip addr del 1.2.3.4/32 dev lo
```

> **Nota:** Adapte o IP `10.100.21.222` para o IP da interface onde o Unbound escuta.
> Verifique com: `grep 'interface:' /etc/unbound/includes/interfaces.conf`

## Simulação de Replies Indesejadas (unwanted.replies)

Este contador é incrementado pelo próprio Unbound quando recebe respostas de servidores autoritativos que não correspondem a queries em andamento. É mais difícil de simular em ambiente controlado, pois depende de tráfego externo anômalo.

Para ativar a proteção com threshold automático, configure no Unbound:

```
server:
    unwanted-reply-threshold: 10000
```

Isso faz o Unbound limpar o cache automaticamente ao atingir o limiar, mitigando ataques de cache poisoning.

## Validação no Dashboard

1. Acesse o painel principal (`index.php`)
2. O card **Anomalias de Rede** deve mostrar:
   - **Queries Recusadas** com o total de queries de IPs não autorizados
   - **Replies Indesejadas** com o total de respostas não solicitadas
3. Os valores atualizam automaticamente a cada 5 segundos via polling

## Reset dos contadores

Os contadores do Unbound são acumulativos desde o último restart do serviço. Para zerar:

```bash
# Reiniciar o serviço zera todos os contadores
sudo systemctl restart unbound

# Ou usar stats (sem _noreset) para resetar apenas os contadores
sudo unbound-control -c /etc/unbound/unbound.conf stats
```

Após o reset, execute `php scripts/aggregate_stats.php` para atualizar o cache.
