/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class GeoipService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Lookup
     * @param ip
     * @returns any Successful Response
     * @throws ApiError
     */
    public lookupApiV1GeoipLookupGet(
        ip: string,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/geoip/lookup',
            query: {
                'ip': ip,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Lookup Bulk
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public lookupBulkApiV1GeoipLookupBulkPost(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/geoip/lookup-bulk',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Top Countries
     * Top países dos clientes BLOCKED (compat — mantido pra /threats.php).
     * @param hours
     * @param limit
     * @returns any Successful Response
     * @throws ApiError
     */
    public topCountriesApiV1GeoipTopCountriesGet(
        hours: number = 24,
        limit: number = 20,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/geoip/top-countries',
            query: {
                'hours': hours,
                'limit': limit,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Distribution
     * Distribuição global de queries por país.
     *
     * action vazio = todas; 'blocked'/'resolved'/'cached'/'nxdomain_upstream' filtra.
     * @param hours
     * @param limit
     * @param action
     * @returns any Successful Response
     * @throws ApiError
     */
    public distributionApiV1GeoipDistributionGet(
        hours: number = 24,
        limit: number = 50,
        action: string = '',
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/geoip/distribution',
            query: {
                'hours': hours,
                'limit': limit,
                'action': action,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Top Asns
     * Top ASNs (provedores/redes) por hits. action='' = todas.
     * @param hours
     * @param limit
     * @param action
     * @returns any Successful Response
     * @throws ApiError
     */
    public topAsnsApiV1GeoipTopAsnsGet(
        hours: number = 24,
        limit: number = 20,
        action: string = 'blocked',
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/geoip/top-asns',
            query: {
                'hours': hours,
                'limit': limit,
                'action': action,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
