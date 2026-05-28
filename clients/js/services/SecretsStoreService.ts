/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class SecretsStoreService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Status
     * @returns any Successful Response
     * @throws ApiError
     */
    public statusApiV1AdminSecretsStoreStatusGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/admin/secrets-store/status',
        });
    }
}
