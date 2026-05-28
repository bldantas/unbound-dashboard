/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { ApplyRequest } from '../models/ApplyRequest';
import type { RestoreRequest } from '../models/RestoreRequest';
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class UpdatesService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Check
     * Consulta GitHub Releases pela última versão publicada. Resposta
     * sempre 200 — se GitHub off, retorna {error: ...} e has_update=false.
     * Cache de 5min em Redis pra não bater GitHub a cada refresh do UI.
     * @returns any Successful Response
     * @throws ApiError
     */
    public checkApiV1UpdatesCheckGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/updates/check',
        });
    }
    /**
     * Apply
     * Dispara o update. Não bloqueia — retorna job_id imediato pra cliente
     * pollear `/status/{job_id}` ou abrir SSE em `/log/{job_id}`.
     *
     * Pipeline completo em `services/updater.apply_update`:
     * - lock global (Redis)
     * - refresh release do GitHub (anti-replay)
     * - download + verifica SHA256
     * - spawn `sudo bash update.sh <tar>` detachado
     * - registra job em Redis + audit trail no DuckDB
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public applyApiV1UpdatesApplyPost(
        requestBody: ApplyRequest,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/updates/apply',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Status Endpoint
     * Estado atual do job.
     * Statuses possíveis: running, succeeded, failed, rolled_back, rollback_failed.
     * @param jobId
     * @returns any Successful Response
     * @throws ApiError
     */
    public statusEndpointApiV1UpdatesStatusJobIdGet(
        jobId: string,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/updates/status/{job_id}',
            path: {
                'job_id': jobId,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * List Backups
     * Lista os últimos backups disponíveis pra restore manual.
     * @returns any Successful Response
     * @throws ApiError
     */
    public listBackupsApiV1UpdatesBackupsGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/updates/backups',
        });
    }
    /**
     * Restore Backup
     * Dispara restore de um backup específico (criado por update.sh anterior).
     * Reusa lock global — só uma operação por vez. Job_id retornado pode
     * ser usado pra acompanhar status/log via endpoints existentes.
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public restoreBackupApiV1UpdatesRestorePost(
        requestBody: RestoreRequest,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/updates/restore',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Log Stream
     * SSE stream do log do update em tempo real.
     *
     * Cliente:
     * const es = new EventSource('/api/v1/updates/log/<job_id>');
     * es.onmessage = (e) => appendLine(e.data);  // linhas do log
     * es.addEventListener('done', (e) => {       // termino
     * const final = JSON.parse(e.data);  // {status, exit_code, ...}
     * es.close();
     * });
     * @param jobId
     * @returns any Successful Response
     * @throws ApiError
     */
    public logStreamApiV1UpdatesLogJobIdGet(
        jobId: string,
    ): CancelablePromise<any> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/updates/log/{job_id}',
            path: {
                'job_id': jobId,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
