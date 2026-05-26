-- V18: workflow approval — 2nd-approver pra ações sensíveis.
--
-- Quando habilitado (setting `workflow_approval_enabled`), endpoints
-- listados em `workflow_approval_actions` (CSV) não executam diretamente:
-- registram um approval_request e devolvem 202 Accepted. Outro admin
-- (diferente do requester) revisa, aprova ou rejeita.
--
-- O caller (router) é responsável por gravar o payload o suficiente pra
-- replay (action + body original). Após aprovado, alguém aciona /execute
-- que dispara o caminho final.

CREATE SEQUENCE IF NOT EXISTS approval_requests_id_seq START 1;

CREATE TABLE IF NOT EXISTS approval_requests (
    id                  INTEGER     PRIMARY KEY DEFAULT nextval('approval_requests_id_seq'),
    created_at          TIMESTAMP   NOT NULL DEFAULT now(),
    requester_id        INTEGER     NOT NULL,
    requester_username  VARCHAR(64),
    requester_ip        VARCHAR(64),
    action              VARCHAR(80) NOT NULL,         -- 'dns_security.apply', 'doh_inbound.gen_cert', etc
    description         VARCHAR(500),                 -- texto livre pra exibir ao approver
    payload             JSON,                         -- snapshot do body original p/ replay
    status              VARCHAR(20) NOT NULL DEFAULT 'pending',
        -- pending | approved | rejected | executed | expired
    approver_id         INTEGER,
    approver_username   VARCHAR(64),
    approved_at         TIMESTAMP,
    rejected_reason     VARCHAR(255),
    executed_at         TIMESTAMP,
    executed_result     JSON,
    expires_at          TIMESTAMP   NOT NULL          -- pending TTL default 24h
);

CREATE INDEX IF NOT EXISTS idx_approvals_status_created
    ON approval_requests (status, created_at);
CREATE INDEX IF NOT EXISTS idx_approvals_expires
    ON approval_requests (expires_at);
