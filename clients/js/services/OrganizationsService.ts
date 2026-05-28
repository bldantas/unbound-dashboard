/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class OrganizationsService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * List Orgs
     * @returns any Successful Response
     * @throws ApiError
     */
    public listOrgsApiV1OrganizationsGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/organizations/',
        });
    }
    /**
     * Create Org
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public createOrgApiV1OrganizationsPost(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/organizations/',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Update Org
     * @param orgId
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public updateOrgApiV1OrganizationsOrgIdPut(
        orgId: number,
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/organizations/{org_id}',
            path: {
                'org_id': orgId,
            },
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Delete Org
     * @param orgId
     * @returns void
     * @throws ApiError
     */
    public deleteOrgApiV1OrganizationsOrgIdDelete(
        orgId: number,
    ): CancelablePromise<void> {
        return this.httpRequest.request({
            method: 'DELETE',
            url: '/api/v1/organizations/{org_id}',
            path: {
                'org_id': orgId,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Assign User
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public assignUserApiV1OrganizationsAssignUserPost(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/organizations/assign-user',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
