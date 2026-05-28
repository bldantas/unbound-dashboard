/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class PoliciesService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * List Policies
     * @returns any Successful Response
     * @throws ApiError
     */
    public listPoliciesApiV1PoliciesGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/policies',
        });
    }
    /**
     * Create Policy
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public createPolicyApiV1PoliciesPost(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/policies',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * List Full Enabled
     * Lista enabled+ranges+blocks+allows. Consumida pelo PHP pra gerar views.conf.
     * @returns any Successful Response
     * @throws ApiError
     */
    public listFullEnabledApiV1PoliciesFullEnabledGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/policies/full-enabled',
        });
    }
    /**
     * Get Policy
     * @param slug
     * @returns any Successful Response
     * @throws ApiError
     */
    public getPolicyApiV1PoliciesSlugGet(
        slug: string,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/policies/{slug}',
            path: {
                'slug': slug,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Update Policy
     * @param slug
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public updatePolicyApiV1PoliciesSlugPatch(
        slug: string,
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PATCH',
            url: '/api/v1/policies/{slug}',
            path: {
                'slug': slug,
            },
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Delete Policy
     * @param slug
     * @returns any Successful Response
     * @throws ApiError
     */
    public deletePolicyApiV1PoliciesSlugDelete(
        slug: string,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'DELETE',
            url: '/api/v1/policies/{slug}',
            path: {
                'slug': slug,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Add Range
     * @param slug
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public addRangeApiV1PoliciesSlugRangesPost(
        slug: string,
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/policies/{slug}/ranges',
            path: {
                'slug': slug,
            },
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Remove Range
     * @param slug
     * @param rangeId
     * @returns any Successful Response
     * @throws ApiError
     */
    public removeRangeApiV1PoliciesSlugRangesRangeIdDelete(
        slug: string,
        rangeId: number,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'DELETE',
            url: '/api/v1/policies/{slug}/ranges/{range_id}',
            path: {
                'slug': slug,
                'range_id': rangeId,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Add Block
     * @param slug
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public addBlockApiV1PoliciesSlugBlocksPost(
        slug: string,
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/policies/{slug}/blocks',
            path: {
                'slug': slug,
            },
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Remove Block
     * @param slug
     * @param domain
     * @returns any Successful Response
     * @throws ApiError
     */
    public removeBlockApiV1PoliciesSlugBlocksDomainDelete(
        slug: string,
        domain: string,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'DELETE',
            url: '/api/v1/policies/{slug}/blocks/{domain}',
            path: {
                'slug': slug,
                'domain': domain,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Add Allow
     * @param slug
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public addAllowApiV1PoliciesSlugAllowsPost(
        slug: string,
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/policies/{slug}/allows',
            path: {
                'slug': slug,
            },
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Remove Allow
     * @param slug
     * @param domain
     * @returns any Successful Response
     * @throws ApiError
     */
    public removeAllowApiV1PoliciesSlugAllowsDomainDelete(
        slug: string,
        domain: string,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'DELETE',
            url: '/api/v1/policies/{slug}/allows/{domain}',
            path: {
                'slug': slug,
                'domain': domain,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
