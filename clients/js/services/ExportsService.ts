/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class ExportsService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Export Query Logs
     * Retorna query_logs ordenados DESC. Usado pro CSV de logs DNS.
     * @param since Unix epoch — só retorna rows >= since (0 = todos)
     * @returns any Successful Response
     * @throws ApiError
     */
    public exportQueryLogsApiV1ExportsQueryLogsGet(
        since?: number,
    ): CancelablePromise<Array<Record<string, any>>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/exports/query-logs',
            query: {
                'since': since,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Export Stats Report
     * Sumário pra o JSON de stats: daily_history (90d) + top_domains_24h +
     * top_clients_24h. NÃO inclui current_metrics (PHP lê data/latest_stats.json).
     * @returns any Successful Response
     * @throws ApiError
     */
    public exportStatsReportApiV1ExportsStatsReportGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/exports/stats-report',
        });
    }
    /**
     * Export Settings
     * @returns any Successful Response
     * @throws ApiError
     */
    public exportSettingsApiV1ExportsSettingsGet(): CancelablePromise<Array<Record<string, any>>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/exports/settings',
        });
    }
    /**
     * Import Settings Bulk
     * Bulk upsert de settings — usado pelo restore de config backup.
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public importSettingsBulkApiV1ExportsSettingsBulkPost(
        requestBody: Array<Record<string, any>>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/exports/settings/bulk',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Export Blocklist
     * Lista todos blocklist_domains pra export CSV.
     * @returns any Successful Response
     * @throws ApiError
     */
    public exportBlocklistApiV1ExportsBlocklistGet(): CancelablePromise<Array<Record<string, any>>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/exports/blocklist',
        });
    }
}
