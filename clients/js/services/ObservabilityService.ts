/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class ObservabilityService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Get Time Series
     * Série temporal de até 60 samples (1h, 1/min) escrita pelo UnboundCollector.
     * Inclui latência média/mediana, QPS, hits/miss, secure/bogus.
     * @returns any Successful Response
     * @throws ApiError
     */
    public getTimeSeriesApiV1ObservabilityTimeSeriesGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/observability/time-series',
        });
    }
    /**
     * Get Workers Status
     * Status agregado dos workers. Combina:
     * - tasks vivas (do app.state via lifespan)
     * - last_run conhecido (settings ou tabelas próprias)
     * - próximas execuções estimadas (best-effort, baseado no tick)
     * @returns any Successful Response
     * @throws ApiError
     */
    public getWorkersStatusApiV1ObservabilityWorkersGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/observability/workers',
        });
    }
}
