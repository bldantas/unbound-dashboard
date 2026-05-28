/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class AuditService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * List Update Audit
     * Histórico de updates/restores aplicados via UI.
     * @param limit
     * @returns any Successful Response
     * @throws ApiError
     */
    public listUpdateAuditApiV1AuditUpdatesGet(
        limit: number = 50,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/audit/updates',
            query: {
                'limit': limit,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * List Admin Audit
     * Lista filtrada do admin_audit.
     * @param category
     * @param actorId
     * @param actionPrefix
     * @param fromTs
     * @param toTs
     * @param limit
     * @param offset
     * @returns any Successful Response
     * @throws ApiError
     */
    public listAdminAuditApiV1AuditAdminListGet(
        category?: (string | null),
        actorId?: (number | null),
        actionPrefix?: (string | null),
        fromTs?: (number | null),
        toTs?: (number | null),
        limit: number = 100,
        offset?: number,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/audit/admin/list',
            query: {
                'category': category,
                'actor_id': actorId,
                'action_prefix': actionPrefix,
                'from_ts': fromTs,
                'to_ts': toTs,
                'limit': limit,
                'offset': offset,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Export Admin Audit Csv
     * Export CSV (cap 10k linhas). Loga o próprio export no audit.
     * @param category
     * @param actorId
     * @param actionPrefix
     * @param fromTs
     * @param toTs
     * @returns any Successful Response
     * @throws ApiError
     */
    public exportAdminAuditCsvApiV1AuditAdminExportCsvGet(
        category?: (string | null),
        actorId?: (number | null),
        actionPrefix?: (string | null),
        fromTs?: (number | null),
        toTs?: (number | null),
    ): CancelablePromise<any> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/audit/admin/export-csv',
            query: {
                'category': category,
                'actor_id': actorId,
                'action_prefix': actionPrefix,
                'from_ts': fromTs,
                'to_ts': toTs,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Export Admin Audit Pdf
     * Export PDF (cap 2000 linhas — pra mais use CSV). Loga em audit.
     * @param category
     * @param actorId
     * @param actionPrefix
     * @param fromTs
     * @param toTs
     * @returns any Successful Response
     * @throws ApiError
     */
    public exportAdminAuditPdfApiV1AuditAdminExportPdfGet(
        category?: (string | null),
        actorId?: (number | null),
        actionPrefix?: (string | null),
        fromTs?: (number | null),
        toTs?: (number | null),
    ): CancelablePromise<any> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/audit/admin/export-pdf',
            query: {
                'category': category,
                'actor_id': actorId,
                'action_prefix': actionPrefix,
                'from_ts': fromTs,
                'to_ts': toTs,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Get Audit Retention
     * @returns any Successful Response
     * @throws ApiError
     */
    public getAuditRetentionApiV1AuditAdminRetentionSettingsGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/audit/admin/retention/settings',
        });
    }
    /**
     * Update Audit Retention
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public updateAuditRetentionApiV1AuditAdminRetentionSettingsPut(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/audit/admin/retention/settings',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Prune Admin Audit
     * @returns any Successful Response
     * @throws ApiError
     */
    public pruneAdminAuditApiV1AuditAdminPruneNowPost(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/audit/admin/prune-now',
        });
    }
}
