/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class DohInboundService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Get Info
     * @returns any Successful Response
     * @throws ApiError
     */
    public getInfoApiV1DohInboundInfoGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/doh-inbound/info',
        });
    }
    /**
     * Gen Cert
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public genCertApiV1DohInboundGenCertPost(
        requestBody?: Record<string, any>,
    ): CancelablePromise<any> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/doh-inbound/gen-cert',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
