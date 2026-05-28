/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class ThreatsService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Threats Data
     * @param limit 10|20|50|100|'todos'
     * @param clientIp Filtro exato por IP cliente — clica no chip do Top
     * @param domain Filtro exato por domínio — clica no chip do Top
     * @returns any Successful Response
     * @throws ApiError
     */
    public threatsDataApiV1ThreatsDataGet(
        limit: string = '10',
        clientIp: string = '',
        domain: string = '',
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/threats/data',
            query: {
                'limit': limit,
                'client_ip': clientIp,
                'domain': domain,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
