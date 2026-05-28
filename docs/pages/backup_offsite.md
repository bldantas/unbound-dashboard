# `/backup_offsite.php` — Backup S3-compatible (multi-destination)

Backup automático do DuckDB + configs do Unbound + env file pra serviços S3-compatible: **AWS S3, MinIO, Wasabi, Cloudflare R2, Backblaze B2**.

**Disponível desde:** v2.45+ (single destination); v2.79 (multi-destination); v2.101.0 (cache de tarball compartilhado).

---

## O que é incluído no backup

1. **DuckDB** (`/var/lib/unbound-dashboard/unbound_dash.duckdb`) — todo o estado da aplicação
2. **Configs do Unbound** (`/etc/unbound/`) — exceto certificados TLS + blocked_domains.conf (auto-gerado)
3. **`/etc/unbound-dashboard/api-v1.env`** (cifrado se SECRETS_MASTER_KEY presente — caso contrário, plaintext com warning)

Tarball gzip nomeado `unbound-backup-<hostname>-<YYYYMMDD-HHMMSS>.tar.gz`.

---

## Multi-destination (v2.79+)

Cada destination tem:
- **Label** (ex: "AWS Primary", "Wasabi DR")
- **Endpoint** (vazio = AWS default; preencha pra MinIO/Wasabi/R2/B2)
- **Bucket** + **Region** + **Prefix** (opcional, subpasta)
- **Access key** + **Secret key** (este vai cifrado se master key configurada)
- **Retention count** (default 10 — quantos backups manter; mais antigos são deletados via `delete_object` no S3)
- **Priority** (0..1000, ordena upload em DESC — destinos prioritários sobem primeiro)
- **Enabled** (toggle)

**Cache de tarball compartilhado (v2.101.0):** `backup_destinations_service.upload_to_all()` compila o tarball **1 vez** via `create_archive()` e passa o path pra cada destino (`upload_backup(prebuilt_archive=..., cleanup=False)`). Cleanup central no `finally` quando todos terminam. Pra N destinos com DuckDB grande, isso vira diferença de minutos.

---

## Quando o backup é disparado

| Trigger | Frequência |
|---|---|
| `BackupUploader` worker | Diário (1x, hora configurável em settings) |
| Botão "Disparar upload agora" na UI | Manual |
| `POST /api/v1/backup-offsite/run-now` | API |

Todos os triggers passam pelo mesmo `upload_to_all()` — semântica idêntica.

Status global do último upload:
- **ok**: todos os destinos succeederam
- **partial**: ≥1 sucesso + ≥1 erro
- **error**: 0 sucessos

---

## Restore

**Pelo `/backup_offsite.php`:**
- Lista os últimos N backups por destino
- Botão "Restaurar este" → modal de confirmação dupla → tarball baixado, validado (SHA256 opcional), aplicado em transação

**Manualmente (recomendado pra DR real):**
```bash
sudo systemctl stop unbound-dashboard-api
# Baixa tarball do S3 e extrai em /
sudo tar xzf unbound-backup-<host>-<TS>.tar.gz -C /
sudo systemctl start unbound-dashboard-api
```

---

## Restore test runner (smoke)

Worker `RestoreTestRunner` periódico (configurável, default semanal):
- Baixa último backup do destino prioritário
- Extrai em DuckDB temporário em `/tmp`
- Roda query smoke (`SELECT COUNT(*) FROM users`)
- Loga `restore_test.passed` / `restore_test.failed`
- Não toca no DuckDB real

Pra **garantir** que backups funcionam — não só que upload teve sucesso. Aprendido depois do incidente clássico "tinha backup mas restore não funcionava".

---

## Troubleshooting

| Sintoma | Causa provável |
|---|---|
| `S3 403 AccessDenied` | Bucket policy ou IAM. Token precisa de `s3:PutObject` + `s3:ListBucket` (pra retention) + `s3:DeleteObject` (pra retention) |
| `S3 NoSuchBucket` | Bucket não existe ou region errada |
| Upload demora 5min+ pra cada destino | DuckDB ficou gigante (esperado em ambiente com ~10M+ query_logs). Considere reduzir retenção em `/notifications.php` |
| `secret_key` aparece em plaintext nos logs | Configure `SECRETS_MASTER_KEY` no env e reinicie — secrets_migrator cifra automaticamente |
| Restore falha com "DuckDB busy" | Pare o service antes (`systemctl stop unbound-dashboard-api`) e refaça |

---

## Endpoints relacionados

| Endpoint | Função |
|---|---|
| `GET /api/v1/backup-offsite/destinations` | Lista destinos (com `secret_key` mascarado) |
| `POST /api/v1/backup-offsite/destinations` | Cria destino |
| `PUT /api/v1/backup-offsite/destinations/{id}` | Atualiza |
| `DELETE /api/v1/backup-offsite/destinations/{id}` | Remove |
| `POST /api/v1/backup-offsite/run-now` | Dispara upload em todos os enabled |
| `GET /api/v1/backup-offsite/list/{destination_id}` | Lista backups num destino |
| `POST /api/v1/backup-offsite/restore` | Restaura backup específico |

---

## Limitações conhecidas

- **Sem incremental** — cada backup é o DuckDB inteiro. Pra ambientes com DuckDB > 1 GB pode pesar
- **Sem encryption-at-rest custom** — usa SSE do S3 se ativado no bucket; tarball em si não é cifrado
- **Retention é por-destination, não por-data** — não dá pra "guardar todos de janeiro + 1/mês de fevereiro pra dezembro"; é só "mantém os N mais recentes"
- **Restore não rolla migrações** — se você restaurou backup de v2.X em sistema rodando v2.Y, as migrations pulam (idempotentes) mas o schema do backup pode estar desatualizado
