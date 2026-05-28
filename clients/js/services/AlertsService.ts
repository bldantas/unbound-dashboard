/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { ThresholdsUpdateRequest } from '../models/ThresholdsUpdateRequest';
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class AlertsService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * List Alerts
     * @returns any Successful Response
     * @throws ApiError
     */
    public listAlertsApiV1AlertsListGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/alerts/list',
        });
    }
    /**
     * Resolve Alert
     * @param alertId
     * @returns void
     * @throws ApiError
     */
    public resolveAlertApiV1AlertsAlertIdResolvePost(
        alertId: number,
    ): CancelablePromise<void> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/alerts/{alert_id}/resolve',
            path: {
                'alert_id': alertId,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Clear Resolved
     * @returns any Successful Response
     * @throws ApiError
     */
    public clearResolvedApiV1AlertsClearResolvedPost(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/alerts/clear-resolved',
        });
    }
    /**
     * Get Thresholds
     * Retorna os 6 thresholds atuais + defaults. Aberto a qualquer user
     * autenticado (a alerts.php precisa pra exibir os números nos cards de
     * hardware mesmo pra viewer).
     * @returns any Successful Response
     * @throws ApiError
     */
    public getThresholdsApiV1AlertsThresholdsGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/alerts/thresholds',
        });
    }
    /**
     * Update Thresholds
     * UPSERT dos thresholds editáveis. Aceita parcial — só campos
     * presentes no body são gravados. Retorna o estado final atualizado.
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public updateThresholdsApiV1AlertsThresholdsPut(
        requestBody: ThresholdsUpdateRequest,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/alerts/thresholds',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
