-- V10: corrige URL do Hagezi Normal. O repo `hagezi/dns-blocklists` renomeou
-- `hosts/normal.txt` → `hosts/multi.txt` (validado em 2026-05-25: normal.txt
-- agora retorna 404). Mantém slug 'hagezi_normal' pra preservar entries
-- existentes e evitar quebrar referências.

UPDATE blocklist_sources
   SET url        = 'https://raw.githubusercontent.com/hagezi/dns-blocklists/main/hosts/multi.txt',
       last_error = NULL
 WHERE slug = 'hagezi_normal';
