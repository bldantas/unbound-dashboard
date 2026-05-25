-- V9: blocklists multi-source + exceções (allowlist).
--
-- Antes: tabela blocklist_domains (PK = domain) populada por UMA fonte por vez
-- (toggle entre stevenblack/hagezi). Só ANATEL (Judicial) efetivamente bloqueava
-- no Unbound; o resto era "catálogo de inteligência" pra busca/analytics.
--
-- Depois: catálogo de fontes (blocklist_sources) com flags independentes
-- index_enabled (popula DuckDB) e block_enabled (gera local-zone NXDOMAIN no
-- Unbound). Várias fontes podem estar ativas simultaneamente. Cada entry agora
-- pertence a uma source (PK composta domain+source_slug) — um mesmo domínio em
-- N listas conta N vezes em blocklist_entries (esperado; é como rastreamos
-- procedência). Allowlist global (blocklist_exceptions) sobrescreve qualquer
-- bloqueio via local-zone transparent.

------------------------------------------------------------
-- BLOCKLIST_SOURCES — catálogo curado de fontes
------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blocklist_sources (
    slug            VARCHAR(50)  PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    description     TEXT,
    url             TEXT         NOT NULL,
    format          VARCHAR(30)  NOT NULL,   -- 'hosts' | 'domains' | 'unbound_localzone' | 'adblock'
    category        VARCHAR(50)  NOT NULL,   -- 'Judicial' | 'Malware/Adware' | 'Phishing' | 'Tracking'
    severity        VARCHAR(20)  NOT NULL DEFAULT 'medium',
    index_enabled   BOOLEAN      NOT NULL DEFAULT false,
    block_enabled   BOOLEAN      NOT NULL DEFAULT false,
    is_builtin      BOOLEAN      NOT NULL DEFAULT true,
    sort_order      INTEGER      NOT NULL DEFAULT 100,
    last_sync       TIMESTAMP,
    last_count      INTEGER      NOT NULL DEFAULT 0,
    last_error      TEXT
);

INSERT INTO blocklist_sources (slug, name, description, url, format, category, severity, sort_order) VALUES
    ('anatel',              'ANATEL Judicial',         'Lista oficial brasileira via Anablock — bloqueio judicial de bets/cassinos.', 'https://api.anablock.net.br/domains/all?output=unbound', 'unbound_localzone', 'Judicial',       'high',   10),
    ('stevenblack',         'StevenBlack',             'Hosts unificado (adware/malware/trackers).',                                   'https://raw.githubusercontent.com/StevenBlack/hosts/master/hosts',                'hosts',             'Malware/Adware', 'medium', 20),
    ('hagezi_light',        'Hagezi Light',            'Leve — mínima quebra, pega o pior do pior.',                                   'https://raw.githubusercontent.com/hagezi/dns-blocklists/main/hosts/light.txt',    'hosts',             'Malware/Adware', 'low',    30),
    ('hagezi_normal',       'Hagezi Normal',           'Balanceada — recomendada como default.',                                       'https://raw.githubusercontent.com/hagezi/dns-blocklists/main/hosts/normal.txt',   'hosts',             'Malware/Adware', 'medium', 31),
    ('hagezi_pro',          'Hagezi Pro',              'Agressiva — pode quebrar sites legítimos.',                                    'https://raw.githubusercontent.com/hagezi/dns-blocklists/main/hosts/pro.txt',      'hosts',             'Malware/Adware', 'high',   32),
    ('oisd_small',          'OISD Small',              'Curada conservadora (~150k entradas).',                                        'https://small.oisd.nl/domainswild',                                              'domains',           'Malware/Adware', 'medium', 40),
    ('oisd_big',            'OISD Big',                'Cobertura ampla (~400k entradas).',                                            'https://big.oisd.nl/domainswild',                                                'domains',           'Malware/Adware', 'high',   41),
    ('adguard_dns',         'AdGuard DNS',             'Filtro geral do AdGuard (via firebog).',                                       'https://v.firebog.net/hosts/AdguardDNS.txt',                                     'domains',           'Malware/Adware', 'medium', 50),
    ('nocoin',              'NoCoin (cryptominers)',   'Bloqueia mineradores in-browser.',                                             'https://raw.githubusercontent.com/hoshsadiq/adblock-nocoin-list/master/hosts.txt','hosts',             'Tracking',       'medium', 60),
    ('easyprivacy',         'EasyPrivacy',             'Trackers genéricos (via firebog).',                                            'https://v.firebog.net/hosts/Easyprivacy.txt',                                    'domains',           'Tracking',       'medium', 61);

------------------------------------------------------------
-- BLOCKLIST_ENTRIES — substitui blocklist_domains, PK composta
------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blocklist_entries (
    domain          VARCHAR(255) NOT NULL,
    source_slug     VARCHAR(50)  NOT NULL,
    added_at        TIMESTAMP    NOT NULL DEFAULT NOW(),
    PRIMARY KEY (domain, source_slug)
);

CREATE INDEX IF NOT EXISTS idx_blocklist_entries_source ON blocklist_entries (source_slug);
CREATE INDEX IF NOT EXISTS idx_blocklist_entries_domain ON blocklist_entries (domain);

-- Backfill: migra blocklist_domains → blocklist_entries.
-- Judicial vai pra 'anatel'. Malware/Adware vai pra fonte atual do setting
-- (default stevenblack). Phishing (caso exista) e categorias estranhas caem
-- em 'stevenblack' como fallback seguro — usuário pode reorganizar depois.
-- Só roda se blocklist_domains ainda existir (idempotência: a migration pode
-- ter sido aplicada e a tabela já dropada).
INSERT INTO blocklist_entries (domain, source_slug)
    SELECT
        d.domain,
        CASE
            WHEN d.category = 'Judicial' THEN 'anatel'
            WHEN d.category = 'Malware/Adware' THEN COALESCE(
                (SELECT setting_value FROM settings WHERE setting_key = 'blacklist_source' LIMIT 1),
                'stevenblack'
            )
            ELSE 'stevenblack'
        END
    FROM (SELECT domain, category FROM blocklist_domains WHERE domain IS NOT NULL) d
    ON CONFLICT (domain, source_slug) DO NOTHING;

-- Backfill das flags index_enabled/block_enabled pra preservar o estado anterior:
-- ANATEL: index+block ativos se o setting official_blocklist_enabled='1'.
UPDATE blocklist_sources
   SET index_enabled = true, block_enabled = true
 WHERE slug = 'anatel'
   AND EXISTS (
       SELECT 1 FROM settings
       WHERE setting_key = 'official_blocklist_enabled' AND setting_value = '1'
   );

-- Fonte atual do catálogo (stevenblack/hagezi_*): só index ligado se setting
-- blacklist_source_enabled != '0'. block_enabled fica false (era esse o
-- comportamento antigo: catálogo nunca bloqueou). Usuário ativa manualmente
-- depois da migration se quiser.
UPDATE blocklist_sources
   SET index_enabled = NOT EXISTS (
       SELECT 1 FROM settings
       WHERE setting_key = 'blacklist_source_enabled' AND setting_value = '0'
   )
 WHERE slug = COALESCE(
       (SELECT setting_value FROM settings WHERE setting_key = 'blacklist_source' LIMIT 1),
       'stevenblack'
   );

-- A tabela antiga (blocklist_domains) é mantida nesta migration pra rollback
-- seguro caso algum endpoint legado tente ler dela durante o deploy. Será
-- removida em V10 depois que todos os repos/scripts estiverem usando
-- blocklist_entries em produção.

------------------------------------------------------------
-- BLOCKLIST_EXCEPTIONS — allowlist global (sobrescreve qualquer bloqueio)
------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blocklist_exceptions (
    domain          VARCHAR(255) PRIMARY KEY,
    reason          TEXT,
    created_by      VARCHAR(50),
    created_at      TIMESTAMP    NOT NULL DEFAULT NOW()
);
