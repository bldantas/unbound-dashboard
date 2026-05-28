/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class RateLimitsService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Get Config
     * Retorna config atual (env + settings DB) — UI mostra qual está vigente.
     * @returns any Successful Response
     * @throws ApiError
     */
    public getConfigApiV1RateLimitsConfigGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/rate-limits/config',
        });
    }
    /**
     * Update Config
     * Persiste novos limites em settings. Aplica após restart do API.
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public updateConfigApiV1RateLimitsConfigPut(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/rate-limits/config',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
