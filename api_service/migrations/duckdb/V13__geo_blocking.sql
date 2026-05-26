-- V13: geo-blocking — bloqueio de países inteiros via access-control do Unbound.
--
-- geo_blocks guarda os CIDRs IPv4/IPv6 baixados por país (de iwik.org) +
-- flag "blocked" indicando se o país está ativo no include do Unbound.
-- O include /etc/unbound/includes/geo_acl.conf é gerado a partir dessa
-- tabela pelo geo_blocking_service.apply().
--
-- Padrão: cidrs_ipv4 / cidrs_ipv6 são listas separadas por newline
-- (NOT JSON pra evitar overhead em parse a cada apply). updated_at indica
-- a última vez que baixou de iwik.org com sucesso.

CREATE SEQUENCE IF NOT EXISTS geo_blocks_id_seq START 1;
CREATE TABLE IF NOT EXISTS geo_blocks (
    id              BIGINT      PRIMARY KEY DEFAULT nextval('geo_blocks_id_seq'),
    country_code    VARCHAR(2)  NOT NULL UNIQUE,
    country_name    VARCHAR(120) NOT NULL,
    blocked         BOOLEAN     NOT NULL DEFAULT TRUE,
    cidrs_ipv4      TEXT        NOT NULL DEFAULT '',
    cidrs_ipv6      TEXT        NOT NULL DEFAULT '',
    ipv4_count      INTEGER     NOT NULL DEFAULT 0,
    ipv6_count      INTEGER     NOT NULL DEFAULT 0,
    updated_at      INTEGER     NOT NULL DEFAULT 0,
    last_error      VARCHAR(255) NOT NULL DEFAULT ''
);
