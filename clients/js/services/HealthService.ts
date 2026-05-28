/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class HealthService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Healthz
     * Liveness probe simples — sem dependências externas (DB, Redis).
     * @returns string Successful Response
     * @throws ApiError
     */
    public healthzApiV1HealthzGet(): CancelablePromise<Record<string, string>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/healthz',
        });
    }
}
