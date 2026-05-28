/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class ApprovalsService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Get Config
     * @returns any Successful Response
     * @throws ApiError
     */
    public getConfigApiV1ApprovalsConfigGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/approvals/config',
        });
    }
    /**
     * Update Config
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public updateConfigApiV1ApprovalsConfigPut(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/approvals/config',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * List Pending
     * @returns any Successful Response
     * @throws ApiError
     */
    public listPendingApiV1ApprovalsPendingGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/approvals/pending',
        });
    }
    /**
     * List All
     * @param limit
     * @returns any Successful Response
     * @throws ApiError
     */
    public listAllApiV1ApprovalsListGet(
        limit: number = 200,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/approvals/list',
            query: {
                'limit': limit,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Approve
     * @param requestId
     * @returns any Successful Response
     * @throws ApiError
     */
    public approveApiV1ApprovalsRequestIdApprovePost(
        requestId: number,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/approvals/{request_id}/approve',
            path: {
                'request_id': requestId,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Reject
     * @param requestId
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public rejectApiV1ApprovalsRequestIdRejectPost(
        requestId: number,
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/approvals/{request_id}/reject',
            path: {
                'request_id': requestId,
            },
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Execute
     * Dispatcha o handler registrado da action. Replay automático sem
     * precisar do request HTTP original.
     * @param requestId
     * @returns any Successful Response
     * @throws ApiError
     */
    public executeApiV1ApprovalsRequestIdExecutePost(
        requestId: number,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/approvals/{request_id}/execute',
            path: {
                'request_id': requestId,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * List Handlers
     * Quais actions têm handler dispatchável automaticamente.
     * @returns any Successful Response
     * @throws ApiError
     */
    public listHandlersApiV1ApprovalsHandlersGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/approvals/handlers',
        });
    }
    /**
     * Mark Executed
     * @param requestId
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public markExecutedApiV1ApprovalsRequestIdMarkExecutedPost(
        requestId: number,
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/approvals/{request_id}/mark-executed',
            path: {
                'request_id': requestId,
            },
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
