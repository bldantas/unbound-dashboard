/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class NotificationsService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Feed
     * Bell payload — só ativos + não-dismissed, mais recente primeiro.
     * @param limit
     * @returns any Successful Response
     * @throws ApiError
     */
    public feedApiV1NotificationsFeedGet(
        limit: number = 30,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/notifications/feed',
            query: {
                'limit': limit,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * List Full
     * Feed completo pra página dedicada — com filtros e paginação.
     * @param severity
     * @param typePrefix
     * @param resolved
     * @param dismissed
     * @param limit
     * @param offset
     * @returns any Successful Response
     * @throws ApiError
     */
    public listFullApiV1NotificationsListGet(
        severity?: (string | null),
        typePrefix?: (string | null),
        resolved?: (boolean | null),
        dismissed?: (boolean | null),
        limit: number = 50,
        offset?: number,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/notifications/list',
            query: {
                'severity': severity,
                'type_prefix': typePrefix,
                'resolved': resolved,
                'dismissed': dismissed,
                'limit': limit,
                'offset': offset,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Dismiss
     * Mark-as-read server-side. Some do bell sem resolver o alerta.
     * @param alertId
     * @returns void
     * @throws ApiError
     */
    public dismissApiV1NotificationsAlertIdDismissPost(
        alertId: number,
    ): CancelablePromise<void> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/notifications/{alert_id}/dismiss',
            path: {
                'alert_id': alertId,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Dismiss All
     * Mark-all-as-read. Usado pelo botão 'Marcar todas como lidas' do bell.
     * @returns any Successful Response
     * @throws ApiError
     */
    public dismissAllApiV1NotificationsDismissAllPost(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/notifications/dismiss-all',
        });
    }
    /**
     * Get Retention
     * @returns any Successful Response
     * @throws ApiError
     */
    public getRetentionApiV1NotificationsRetentionSettingsGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/notifications/retention/settings',
        });
    }
    /**
     * Update Retention
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public updateRetentionApiV1NotificationsRetentionSettingsPut(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/notifications/retention/settings',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Prune Now
     * Roda manualmente o prune (admin-only, usa setting atual).
     * @returns any Successful Response
     * @throws ApiError
     */
    public pruneNowApiV1NotificationsPruneNowPost(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/notifications/prune-now',
        });
    }
    /**
     * Get My Prefs
     * @returns any Successful Response
     * @throws ApiError
     */
    public getMyPrefsApiV1NotificationsPrefsGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/notifications/prefs',
        });
    }
    /**
     * Update My Prefs
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public updateMyPrefsApiV1NotificationsPrefsPut(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/notifications/prefs',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
