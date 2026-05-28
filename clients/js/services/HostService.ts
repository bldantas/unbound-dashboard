/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class HostService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Host Info
     * Info estática do host. Cacheável aggressively no master — só muda
     * se a máquina for renomeada ou OS for atualizado.
     * @returns any Successful Response
     * @throws ApiError
     */
    public hostInfoApiV1HostInfoGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/host/info',
        });
    }
    /**
     * Host Status
     * Snapshot agregado pro master polar. Inclui métricas que mudam em
     * tempo real — não cachear no master.
     *
     * Campos:
     * - version: VERSION local
     * - uptime_seconds: tempo desde boot do api_service
     * - alerts_active: count de alertas não-resolvidos
     * - users_total: total de users cadastrados
     * - sessions_active: total de sessões trackadas (Redis ou DuckDB)
     * - queries_24h: total de query_logs nas últimas 24h
     * - hit_ratio_24h: % de cache hits últimas 24h
     * - duckdb_ok: True se SELECT 1 rolou
     * - auth_kind: como o caller se autenticou (jwt | api_token)
     * @returns any Successful Response
     * @throws ApiError
     */
    public hostStatusApiV1HostStatusGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/host/status',
        });
    }
    /**
     * Host Storage
     * Storage + Redis health pra widget do dashboard.
     *
     * - duckdb_bytes / duckdb_size_human: tamanho do arquivo principal
     * - disk_total / disk_free / disk_used_pct: do mount onde o DuckDB está
     * - redis_ok / redis_latency_ms: ping síncrono
     * - workers_dir_bytes: total dos logs/WAL aux (best-effort)
     * @returns any Successful Response
     * @throws ApiError
     */
    public hostStorageApiV1HostStorageGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/host/storage',
        });
    }
    /**
     * Restart Service
     * Reinicia um serviço whitelisted (api | unbound). Spawn detachado:
     * o systemctl roda em session group novo, sobrevive se o caller for o
     * próprio api_service sendo morto.
     *
     * Pedida pelo master multi-host nos batch ops; também útil localmente.
     * @param service
     * @returns any Successful Response
     * @throws ApiError
     */
    public restartServiceApiV1HostRestartServicePost(
        service: string,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/host/restart/{service}',
            path: {
                'service': service,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Apply Config
     * Recebe payload de config do master e aplica.
     *
     * Body shape:
     * {
         * "blocklists": [{slug, url, index_enabled, block_enabled}, ...],
         * "policies":   [{slug, name, enabled, ranges, blocks, allows}, ...]
         * }
         *
         * Retorno: counts por seção. Aplicação é aditiva (não remove o que
         * não estiver no payload). Re-sync das blocklists e re-gen das views
         * do Unbound não é disparada aqui — o agent tem seus workers/jobs
         * próprios pra isso (BlocklistSyncer roda 1x/h).
         * @param requestBody
         * @returns any Successful Response
         * @throws ApiError
         */
        public applyConfigApiV1HostApplyConfigPost(
            requestBody: Record<string, any>,
        ): CancelablePromise<Record<string, any>> {
            return this.httpRequest.request({
                method: 'POST',
                url: '/api/v1/host/apply-config',
                body: requestBody,
                mediaType: 'application/json',
                errors: {
                    422: `Validation Error`,
                },
            });
        }
    }
