# Changelog

## v1.0.3 - 2026-04-28
- Performance: criado índice composto `idx_action_ts (action, timestamp)` em `query_logs` — elimina full scan de 16M linhas nas queries `WHERE action='blocked'`.
- Performance: adicionada coluna `blocked_count` em `daily_stats` e backfill histórico de 31 dias de dados pré-agregados.
- Performance: `api/threats_data.php` passa a usar `daily_stats` para totais (consulta em 31 linhas em vez de 16M), com fallback automático.
- Performance: `log_ingester.php` atualiza `daily_stats` a cada inserção, mantendo os totais sempre atualizados.
- Performance: `getTopDomains()` em `UnboundManager` limita consulta às últimas 24h para evitar GROUP BY em tabela inteira.
- Update: criado `scripts/migrate_db.sql` com migrações idempotentes — executado automaticamente pelo `update.sh` em cada atualização.
- Schema: `scripts/init_db.sql` atualizado com índice composto e coluna `blocked_count`.

## v1.0.2 - 2026-04-28
- DB: removido índice duplicado `idx_query_logs_domain` em `query_logs` (duplicata de `idx_domain`).
- DB: adicionados índices `idx_alerts_resolved_at` e `idx_alerts_started_at` em `alerts` para otimizar consultas por status e ordenação por data.
- Schema: `scripts/init_db.sql` atualizado com os índices corretos para novas instalações.

## v1.0.1 - 2026-04-23
- Performance: carregamento progressivo aplicado em History e Threats com flush inicial e overlay de loading.
- Threats: adicionado seletor de exibicao de linhas (10, 20, 50, 100, todos) com default em 10.
- Performance: carregamento progressivo tambem aplicado em Logs e Alerts.
- UX: corrigido hide do loader global ao finalizar render para evitar overlay preso.
- Build update: hardening para nao incluir credenciais (src/Database.php) e excluir JSONs volateis de src/data.
- Update script: sincronizacao de src preservando src/data local do servidor.
- Versionamento: build de update agora le VERSION e faz bump automatico por padrao (patch).

## v1.0.0 - 2026-04-23
- Primeira versao estavel do Unbound Dashboard.
- Monitoramento em tempo real de metricas e historico DNS.
- Modulos de seguranca, logs, alertas e diagnostico.
- Ferramentas de exportacao, benchmark e gerenciamento operacional.