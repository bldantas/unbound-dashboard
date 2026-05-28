/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class GeoBlockingService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Status
     * Settings + lista de países + preview do include atual.
     * @returns any Successful Response
     * @throws ApiError
     */
    public statusApiV1GeoBlockingStatusGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/geo-blocking/status',
        });
    }
    /**
     * Update Settings
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public updateSettingsApiV1GeoBlockingSettingsPut(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/geo-blocking/settings',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Add Country
     * body: {country_code, country_name, blocked?: true, refresh?: true}.
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public addCountryApiV1GeoBlockingCountriesPost(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/geo-blocking/countries',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Remove Country
     * @param countryCode
     * @returns any Successful Response
     * @throws ApiError
     */
    public removeCountryApiV1GeoBlockingCountriesCountryCodeDelete(
        countryCode: string,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'DELETE',
            url: '/api/v1/geo-blocking/countries/{country_code}',
            path: {
                'country_code': countryCode,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Set Blocked
     * @param countryCode
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public setBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPut(
        countryCode: string,
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/geo-blocking/countries/{country_code}/blocked',
            path: {
                'country_code': countryCode,
            },
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Refresh Country
     * @param countryCode
     * @returns any Successful Response
     * @throws ApiError
     */
    public refreshCountryApiV1GeoBlockingCountriesCountryCodeRefreshPost(
        countryCode: string,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/geo-blocking/countries/{country_code}/refresh',
            path: {
                'country_code': countryCode,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Refresh All
     * @param onlyBlocked
     * @returns any Successful Response
     * @throws ApiError
     */
    public refreshAllApiV1GeoBlockingRefreshAllPost(
        onlyBlocked: boolean = true,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/geo-blocking/refresh-all',
            query: {
                'only_blocked': onlyBlocked,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Preview
     * @returns any Successful Response
     * @throws ApiError
     */
    public previewApiV1GeoBlockingPreviewGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/geo-blocking/preview',
        });
    }
    /**
     * Apply
     * @returns any Successful Response
     * @throws ApiError
     */
    public applyApiV1GeoBlockingApplyPost(): CancelablePromise<any> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/geo-blocking/apply',
        });
    }
}
