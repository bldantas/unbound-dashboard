/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class HistoryService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * History Summary
     * @param limit 10|20|50|100|'todos'
     * @returns any Successful Response
     * @throws ApiError
     */
    public historySummaryApiV1HistorySummaryGet(
        limit: string = '10',
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/history/summary',
            query: {
                'limit': limit,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
