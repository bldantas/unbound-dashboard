/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class UnboundService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Unbound Stats
     * Sumário do daemon Unbound — qps, hit_ratio, latência, DNSSEC, blocks, etc.
     *
     * Cache TTL 60s (idêntico ao cron `aggregate_stats.php`). Múltiplas requests
     * em paralelo esperam o mesmo build (lock interno).
     *
     * Substitui leitura direta de `data/latest_stats.json` via `api/stats.php`.
     * @returns any Successful Response
     * @throws ApiError
     */
    public unboundStatsApiV1UnboundStatsGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/unbound/stats',
        });
    }
}
