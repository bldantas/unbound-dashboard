-- V8: histórico de polls por host (multi-host).
--
-- Cada linha = uma execução de poll_host. Retenção = últimos 100 por
-- host (trim ON INSERT no service). Permite renderizar sparkline de
-- status + tabela detalhada na aba "Histórico" do modal drill-down.
--
-- payload é JSON do /host/status quando status=ok, NULL quando falha
-- (a info útil aí é só o error).

CREATE SEQUENCE IF NOT EXISTS host_poll_history_id_seq START 1;

CREATE TABLE IF NOT EXISTS host_poll_history (
    id          BIGINT       PRIMARY KEY DEFAULT nextval('host_poll_history_id_seq'),
    host_id     INTEGER      NOT NULL,        -- FK lógica pra managed_hosts.id
    polled_at   TIMESTAMP    NOT NULL DEFAULT NOW(),
    status      VARCHAR(20)  NOT NULL,        -- ok | auth_failed | unreachable | error
    error       TEXT,                          -- não-nulo só quando status != ok
    payload     TEXT                           -- JSON do /host/status (só quando ok)
);

-- Lookup principal: últimos N por host, descendente
CREATE INDEX IF NOT EXISTS idx_host_poll_history_host_polled
    ON host_poll_history (host_id, polled_at DESC);
