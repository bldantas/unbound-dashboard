/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { TestRequest } from '../models/TestRequest';
import type { WebhookConfig } from '../models/WebhookConfig';
import type { WebhookUpdate } from '../models/WebhookUpdate';
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class WebhooksService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Get Config
     * @returns WebhookConfig Successful Response
     * @throws ApiError
     */
    public getConfigApiV1WebhooksConfigGet(): CancelablePromise<WebhookConfig> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/webhooks/config',
        });
    }
    /**
     * Update Config
     * @param requestBody
     * @returns void
     * @throws ApiError
     */
    public updateConfigApiV1WebhooksConfigPut(
        requestBody: WebhookUpdate,
    ): CancelablePromise<void> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/webhooks/config',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Send Test
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public sendTestApiV1WebhooksTestPost(
        requestBody: TestRequest,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/webhooks/test',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
