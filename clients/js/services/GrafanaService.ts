/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class GrafanaService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Snapshot
     * Lista flat de métricas atuais. Cada item: {name, value, unit, timestamp}.
     * Formato pensado pro "Infinity" datasource (parser JSON do Grafana).
     * @returns any Successful Response
     * @throws ApiError
     */
    public snapshotApiV1GrafanaSnapshotGet(): CancelablePromise<Array<Record<string, any>>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/grafana/snapshot',
        });
    }
    /**
     * Timeseries
     * Pontos do hourly_stats no formato `[{time: ISO, value: int}]`.
     * Pronto pra Grafana series visualization.
     * @param metric total|blocked
     * @param hours Janela em horas (1..720)
     * @returns any Successful Response
     * @throws ApiError
     */
    public timeseriesApiV1GrafanaTimeseriesGet(
        metric: 'total' | 'blocked' = 'total',
        hours: number = 24,
    ): CancelablePromise<Array<Record<string, any>>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/grafana/timeseries',
            query: {
                'metric': metric,
                'hours': hours,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
