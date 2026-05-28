/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class ExternalHealthService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Report
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public reportApiV1ExternalHealthReportPost(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/external-health/report',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * List Probes
     * @param probeSource
     * @param hours
     * @param limit
     * @returns any Successful Response
     * @throws ApiError
     */
    public listProbesApiV1ExternalHealthListGet(
        probeSource?: (string | null),
        hours: number = 24,
        limit: number = 200,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/external-health/list',
            query: {
                'probe_source': probeSource,
                'hours': hours,
                'limit': limit,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Get Sla
     * @param hours
     * @param probeSource
     * @returns any Successful Response
     * @throws ApiError
     */
    public getSlaApiV1ExternalHealthSlaGet(
        hours: number = 24,
        probeSource?: (string | null),
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/external-health/sla',
            query: {
                'hours': hours,
                'probe_source': probeSource,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * List Sources
     * @param hours
     * @returns any Successful Response
     * @throws ApiError
     */
    public listSourcesApiV1ExternalHealthSourcesGet(
        hours: number = 168,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/external-health/sources',
            query: {
                'hours': hours,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Get Retention
     * @returns any Successful Response
     * @throws ApiError
     */
    public getRetentionApiV1ExternalHealthRetentionSettingsGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/external-health/retention/settings',
        });
    }
    /**
     * Update Retention
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public updateRetentionApiV1ExternalHealthRetentionSettingsPut(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/external-health/retention/settings',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
