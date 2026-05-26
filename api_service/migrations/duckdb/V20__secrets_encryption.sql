-- V20: secrets encryption — colunas pra valores cifrados via cipher_service.
--
-- Estratégia de migração segura:
-- 1. Adiciona colunas novas *_encrypted (lado a lado com as legacy)
-- 2. Aplicação prioriza encrypted no read; cai pra legacy se vazio
-- 3. Aplicação escreve em encrypted nos novos updates (legacy fica como histórico)
-- 4. Quando admin rodar `tools/encrypt_existing.py`, legacy é cifrado e
--    a antiga zerada (cleanup manual)
--
-- Adiciona também `ha_peers.api_token_raw_encrypted` — opcional, pra
-- healthcheck autenticado. Antes só guardávamos bcrypt do token (sem
-- como recuperar pra fazer GET /health com X-Api-Token). Agora se o
-- operador fornecer keep_raw=true na criação, guardamos o raw cifrado
-- e o monitor consegue se autenticar nos peers.

ALTER TABLE oidc_config ADD COLUMN IF NOT EXISTS client_secret_encrypted VARCHAR(1000);

ALTER TABLE ha_peers ADD COLUMN IF NOT EXISTS api_token_raw_encrypted VARCHAR(1000);
