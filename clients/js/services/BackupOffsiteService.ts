/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class BackupOffsiteService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Get Settings
     * @returns any Successful Response
     * @throws ApiError
     */
    public getSettingsApiV1BackupOffsiteSettingsGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/backup-offsite/settings',
        });
    }
    /**
     * Update Settings
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public updateSettingsApiV1BackupOffsiteSettingsPut(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/backup-offsite/settings',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Test Connection
     * @returns any Successful Response
     * @throws ApiError
     */
    public testConnectionApiV1BackupOffsiteTestPost(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/backup-offsite/test',
        });
    }
    /**
     * Upload Now
     * @returns any Successful Response
     * @throws ApiError
     */
    public uploadNowApiV1BackupOffsiteUploadNowPost(): CancelablePromise<any> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/backup-offsite/upload-now',
        });
    }
    /**
     * Restore Test Endpoint
     * Baixa um backup recente (ou key específica se passada) e valida
     * integridade do DuckDB sem restaurar no DB real.
     *
     * Body opcional: `{"key": "s3-key.tar.gz"}` pra testar uma versão específica.
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public restoreTestEndpointApiV1BackupOffsiteRestoreTestPost(
        requestBody?: (Record<string, any> | null),
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/backup-offsite/restore-test',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * List Destinations
     * @returns any Successful Response
     * @throws ApiError
     */
    public listDestinationsApiV1BackupOffsiteDestinationsGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/backup-offsite/destinations',
        });
    }
    /**
     * Create Destination
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public createDestinationApiV1BackupOffsiteDestinationsPost(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/backup-offsite/destinations',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Update Destination
     * @param destId
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public updateDestinationApiV1BackupOffsiteDestinationsDestIdPut(
        destId: number,
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/backup-offsite/destinations/{dest_id}',
            path: {
                'dest_id': destId,
            },
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Delete Destination
     * @param destId
     * @returns void
     * @throws ApiError
     */
    public deleteDestinationApiV1BackupOffsiteDestinationsDestIdDelete(
        destId: number,
    ): CancelablePromise<void> {
        return this.httpRequest.request({
            method: 'DELETE',
            url: '/api/v1/backup-offsite/destinations/{dest_id}',
            path: {
                'dest_id': destId,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Test Destination
     * @param destId
     * @returns any Successful Response
     * @throws ApiError
     */
    public testDestinationApiV1BackupOffsiteDestinationsDestIdTestPost(
        destId: number,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/backup-offsite/destinations/{dest_id}/test',
            path: {
                'dest_id': destId,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Upload All Destinations
     * @returns any Successful Response
     * @throws ApiError
     */
    public uploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPost(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/backup-offsite/destinations/upload-all',
        });
    }
    /**
     * History
     * @param limit
     * @returns any Successful Response
     * @throws ApiError
     */
    public historyApiV1BackupOffsiteHistoryGet(
        limit: number = 100,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/backup-offsite/history',
            query: {
                'limit': limit,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
