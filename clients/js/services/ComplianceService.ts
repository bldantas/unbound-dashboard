/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class ComplianceService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Lgpd Report
     * Dump JSON das queries do client_ip nas últimas N horas.
     * @param clientIp
     * @param hours
     * @param limit
     * @returns any Successful Response
     * @throws ApiError
     */
    public lgpdReportApiV1ComplianceLgpdReportGet(
        clientIp: string,
        hours: number = 24,
        limit: number = 5000,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/compliance/lgpd-report',
            query: {
                'client_ip': clientIp,
                'hours': hours,
                'limit': limit,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Lgpd Report Csv
     * Mesmo que /lgpd-report mas retorna CSV download.
     * @param clientIp
     * @param hours
     * @param limit
     * @returns any Successful Response
     * @throws ApiError
     */
    public lgpdReportCsvApiV1ComplianceLgpdReportCsvGet(
        clientIp: string,
        hours: number = 24,
        limit: number = 5000,
    ): CancelablePromise<any> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/compliance/lgpd-report.csv',
            query: {
                'client_ip': clientIp,
                'hours': hours,
                'limit': limit,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Lgpd Report Pdf
     * LGPD report como PDF A4 (reportlab). Pra grandes volumes, prefira CSV.
     * @param clientIp
     * @param hours
     * @param limit
     * @returns any Successful Response
     * @throws ApiError
     */
    public lgpdReportPdfApiV1ComplianceLgpdReportPdfGet(
        clientIp: string,
        hours: number = 24,
        limit: number = 5000,
    ): CancelablePromise<any> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/compliance/lgpd-report.pdf',
            query: {
                'client_ip': clientIp,
                'hours': hours,
                'limit': limit,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
