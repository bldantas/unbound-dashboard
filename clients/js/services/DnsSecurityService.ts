/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class DnsSecurityService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Get Info
     * @returns any Successful Response
     * @throws ApiError
     */
    public getInfoApiV1DnsSecurityInfoGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/dns-security/info',
        });
    }
    /**
     * Get Settings
     * @returns any Successful Response
     * @throws ApiError
     */
    public getSettingsApiV1DnsSecuritySettingsGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/dns-security/settings',
        });
    }
    /**
     * Update Settings
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public updateSettingsApiV1DnsSecuritySettingsPut(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/dns-security/settings',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Apply
     * @returns any Successful Response
     * @throws ApiError
     */
    public applyApiV1DnsSecurityApplyPost(): CancelablePromise<any> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/dns-security/apply',
        });
    }
    /**
     * Get Ratelimit
     * @returns any Successful Response
     * @throws ApiError
     */
    public getRatelimitApiV1DnsSecurityRatelimitSettingsGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/dns-security/ratelimit/settings',
        });
    }
    /**
     * Update Ratelimit
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public updateRatelimitApiV1DnsSecurityRatelimitSettingsPut(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/dns-security/ratelimit/settings',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Get Privacy
     * @returns any Successful Response
     * @throws ApiError
     */
    public getPrivacyApiV1DnsSecurityPrivacySettingsGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/dns-security/privacy/settings',
        });
    }
    /**
     * Update Privacy
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public updatePrivacyApiV1DnsSecurityPrivacySettingsPut(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/dns-security/privacy/settings',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Get Hardening
     * @returns any Successful Response
     * @throws ApiError
     */
    public getHardeningApiV1DnsSecurityHardeningSettingsGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/dns-security/hardening/settings',
        });
    }
    /**
     * Update Hardening
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public updateHardeningApiV1DnsSecurityHardeningSettingsPut(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/dns-security/hardening/settings',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Get Performance
     * @returns any Successful Response
     * @throws ApiError
     */
    public getPerformanceApiV1DnsSecurityPerformanceSettingsGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/dns-security/performance/settings',
        });
    }
    /**
     * Update Performance
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public updatePerformanceApiV1DnsSecurityPerformanceSettingsPut(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/dns-security/performance/settings',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Performance Metrics
     * Snapshot rico de métricas pra página /performance.php.
     *
     * Combina o dashboard summary (já em cache 60s) com counters extras
     * relevantes pra tuning: prefetch counter, requestlist avg/max, cache
     * memory, hit ratio, P50/P95/P99 (do histograma).
     * @returns any Successful Response
     * @throws ApiError
     */
    public performanceMetricsApiV1DnsSecurityPerformanceMetricsGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/dns-security/performance/metrics',
        });
    }
}
