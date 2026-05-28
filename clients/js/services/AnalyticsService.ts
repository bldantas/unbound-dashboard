/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class AnalyticsService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Get Summary
     * @param window
     * @returns any Successful Response
     * @throws ApiError
     */
    public getSummaryApiV1AnalyticsSummaryGet(
        window: '1h' | '24h' | '7d' | '30d' = '24h',
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/analytics/summary',
            query: {
                'window': window,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Get Timeseries
     * @param window
     * @returns any Successful Response
     * @throws ApiError
     */
    public getTimeseriesApiV1AnalyticsTimeseriesGet(
        window: '1h' | '24h' | '7d' | '30d' = '24h',
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/analytics/timeseries',
            query: {
                'window': window,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Get By Query Type
     * @param window
     * @returns any Successful Response
     * @throws ApiError
     */
    public getByQueryTypeApiV1AnalyticsByQueryTypeGet(
        window: '1h' | '24h' | '7d' | '30d' = '24h',
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/analytics/by-query-type',
            query: {
                'window': window,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Get Top Domains
     * @param window
     * @param limit
     * @param action
     * @returns any Successful Response
     * @throws ApiError
     */
    public getTopDomainsApiV1AnalyticsTopDomainsGet(
        window: '1h' | '24h' | '7d' | '30d' = '24h',
        limit: number = 20,
        action?: ('blocked' | 'resolved' | 'cached' | 'nxdomain_upstream' | null),
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/analytics/top-domains',
            query: {
                'window': window,
                'limit': limit,
                'action': action,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Get Top Clients
     * @param window
     * @param limit
     * @returns any Successful Response
     * @throws ApiError
     */
    public getTopClientsApiV1AnalyticsTopClientsGet(
        window: '1h' | '24h' | '7d' | '30d' = '24h',
        limit: number = 20,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/analytics/top-clients',
            query: {
                'window': window,
                'limit': limit,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Get Action Breakdown
     * @param window
     * @returns any Successful Response
     * @throws ApiError
     */
    public getActionBreakdownApiV1AnalyticsActionBreakdownGet(
        window: '1h' | '24h' | '7d' | '30d' = '24h',
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/analytics/action-breakdown',
            query: {
                'window': window,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Search Queries
     * @param window
     * @param clientIp
     * @param domain
     * @param queryType
     * @param action
     * @param country
     * @param page
     * @param perPage
     * @returns any Successful Response
     * @throws ApiError
     */
    public searchQueriesApiV1AnalyticsQueriesSearchGet(
        window: '1h' | '24h' | '7d' | '30d' = '24h',
        clientIp: string = '',
        domain: string = '',
        queryType: string = '',
        action: string = '',
        country: string = '',
        page: number = 1,
        perPage: number = 50,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/analytics/queries/search',
            query: {
                'window': window,
                'client_ip': clientIp,
                'domain': domain,
                'query_type': queryType,
                'action': action,
                'country': country,
                'page': page,
                'per_page': perPage,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Get Anomaly Settings
     * @returns any Successful Response
     * @throws ApiError
     */
    public getAnomalySettingsApiV1AnalyticsAnomalySettingsGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/analytics/anomaly/settings',
        });
    }
    /**
     * Update Anomaly Settings
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public updateAnomalySettingsApiV1AnalyticsAnomalySettingsPut(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/analytics/anomaly/settings',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Get Anomaly Recent
     * Lista detecções (alerts com type LIKE 'anomaly_%').
     * @param limit
     * @param includeResolved
     * @returns any Successful Response
     * @throws ApiError
     */
    public getAnomalyRecentApiV1AnalyticsAnomalyRecentGet(
        limit: number = 100,
        includeResolved: boolean = false,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/analytics/anomaly/recent',
            query: {
                'limit': limit,
                'include_resolved': includeResolved,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Run Anomaly Now
     * Roda todos os checks uma vez (independente de anomaly_enabled). Útil pra teste.
     * @returns any Successful Response
     * @throws ApiError
     */
    public runAnomalyNowApiV1AnalyticsAnomalyRunNowPost(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/analytics/anomaly/run-now',
        });
    }
    /**
     * List Anomaly Whitelist
     * @returns any Successful Response
     * @throws ApiError
     */
    public listAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/analytics/anomaly/whitelist',
        });
    }
    /**
     * Add Anomaly Whitelist
     * body: {kind, client_ip?, domain_pattern?, detector?, note?}.
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public addAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistPost(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/analytics/anomaly/whitelist',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Delete Anomaly Whitelist
     * @param wid
     * @returns any Successful Response
     * @throws ApiError
     */
    public deleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDelete(
        wid: number,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'DELETE',
            url: '/api/v1/analytics/anomaly/whitelist/{wid}',
            path: {
                'wid': wid,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Get Anomaly Baseline
     * Retorna 168 buckets (hour_of_day × day_of_week) com avg/stddev/n + meta.
     * @returns any Successful Response
     * @throws ApiError
     */
    public getAnomalyBaselineApiV1AnalyticsAnomalyBaselineGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/analytics/anomaly/baseline',
        });
    }
    /**
     * Get Anomaly Baseline Current
     * Última hora completa vs baseline do mesmo bucket. Útil pra mostrar
     * "onde estamos" no heatmap em tempo real.
     * @returns any Successful Response
     * @throws ApiError
     */
    public getAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/analytics/anomaly/baseline/current',
        });
    }
    /**
     * Anomaly Baseline Learn Now
     * Força re-treino do BaselineLearner (1 ciclo). Idempotente.
     * @returns any Successful Response
     * @throws ApiError
     */
    public anomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPost(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/analytics/anomaly/baseline/learn-now',
        });
    }
    /**
     * Anomaly Resolve All
     * Marca todas as detecções anomaly_* ativas como resolved_at=NOW().
     * @returns any Successful Response
     * @throws ApiError
     */
    public anomalyResolveAllApiV1AnalyticsAnomalyResolveAllPost(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/analytics/anomaly/resolve-all',
        });
    }
    /**
     * Export Csv
     * Export CSV — capped em 100k linhas pra evitar OOM.
     * @param window
     * @param clientIp
     * @param domain
     * @param queryType
     * @param action
     * @param country
     * @param limit
     * @returns any Successful Response
     * @throws ApiError
     */
    public exportCsvApiV1AnalyticsQueriesExportCsvGet(
        window: '1h' | '24h' | '7d' | '30d' = '24h',
        clientIp: string = '',
        domain: string = '',
        queryType: string = '',
        action: string = '',
        country: string = '',
        limit: number = 10000,
    ): CancelablePromise<any> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/analytics/queries/export-csv',
            query: {
                'window': window,
                'client_ip': clientIp,
                'domain': domain,
                'query_type': queryType,
                'action': action,
                'country': country,
                'limit': limit,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Get Retention Settings
     * Settings + estado da última execução do pruner.
     * @returns any Successful Response
     * @throws ApiError
     */
    public getRetentionSettingsApiV1AnalyticsRetentionSettingsGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/analytics/retention/settings',
        });
    }
    /**
     * Update Retention Settings
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public updateRetentionSettingsApiV1AnalyticsRetentionSettingsPut(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/analytics/retention/settings',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Prune Now
     * Dispara prune imediato (ignora schedule).
     * @returns any Successful Response
     * @throws ApiError
     */
    public pruneNowApiV1AnalyticsRetentionPruneNowPost(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/analytics/retention/prune-now',
        });
    }
    /**
     * Get Hourly Stats
     * Últimas N horas de hourly_stats. Usado em /observability.
     * @param hours
     * @returns any Successful Response
     * @throws ApiError
     */
    public getHourlyStatsApiV1AnalyticsHourlyGet(
        hours: number = 24,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/analytics/hourly',
            query: {
                'hours': hours,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
