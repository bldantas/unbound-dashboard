/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { HostCreate } from '../models/HostCreate';
import type { HostSetOrg } from '../models/HostSetOrg';
import type { HostUpdate } from '../models/HostUpdate';
import type { PushConfigRequest } from '../models/PushConfigRequest';
import type { UpgradeRequest } from '../models/UpgradeRequest';
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class HostsService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * List Hosts
     * @returns any Successful Response
     * @throws ApiError
     */
    public listHostsApiV1HostsGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/hosts',
        });
    }
    /**
     * Create Host
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public createHostApiV1HostsPost(
        requestBody: HostCreate,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/hosts',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Set Host Org
     * Admin global pode mover qualquer host. User org-scoped só remaneja
     * hosts da própria org (e só pra própria org ou pra global=None se quiser
     * publicar — mas evitamos publicar). Aqui simplificado: só admin global.
     * @param hostId
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public setHostOrgApiV1HostsHostIdOrgPut(
        hostId: number,
        requestBody: HostSetOrg,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/hosts/{host_id}/org',
            path: {
                'host_id': hostId,
            },
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Update Host
     * @param hostId
     * @param requestBody
     * @returns void
     * @throws ApiError
     */
    public updateHostApiV1HostsHostIdPut(
        hostId: number,
        requestBody: HostUpdate,
    ): CancelablePromise<void> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/hosts/{host_id}',
            path: {
                'host_id': hostId,
            },
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Delete Host
     * @param hostId
     * @returns void
     * @throws ApiError
     */
    public deleteHostApiV1HostsHostIdDelete(
        hostId: number,
    ): CancelablePromise<void> {
        return this.httpRequest.request({
            method: 'DELETE',
            url: '/api/v1/hosts/{host_id}',
            path: {
                'host_id': hostId,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Batch Poll
     * Força poll imediato em todos os hosts. Atualiza banco.
     * @returns any Successful Response
     * @throws ApiError
     */
    public batchPollApiV1HostsBatchPollPost(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/hosts/batch/poll',
        });
    }
    /**
     * Batch Restart
     * Restart em todos os hosts. Sequencial — fail isolado por host.
     * @param service
     * @returns any Successful Response
     * @throws ApiError
     */
    public batchRestartApiV1HostsBatchRestartServicePost(
        service: string,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/hosts/batch/restart/{service}',
            path: {
                'service': service,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Batch Upgrade
     * Upgrade em todos os hosts pra `version`. Sequencial.
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public batchUpgradeApiV1HostsBatchUpgradePost(
        requestBody: UpgradeRequest,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/hosts/batch/upgrade',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Poll Now
     * Força poll imediato do host. Retorna resultado.
     * @param hostId
     * @returns any Successful Response
     * @throws ApiError
     */
    public pollNowApiV1HostsHostIdPollPost(
        hostId: number,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/hosts/{host_id}/poll',
            path: {
                'host_id': hostId,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Host Info
     * Proxy: GET /api/v1/host/info do agent (estático: hostname, OS, etc).
     * @param hostId
     * @returns any Successful Response
     * @throws ApiError
     */
    public hostInfoApiV1HostsHostIdInfoGet(
        hostId: number,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/hosts/{host_id}/info',
            path: {
                'host_id': hostId,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Host History
     * Últimos polls registrados pelo poller. Retenção: HISTORY_RETENTION (100).
     * @param hostId
     * @param limit
     * @returns any Successful Response
     * @throws ApiError
     */
    public hostHistoryApiV1HostsHostIdHistoryGet(
        hostId: number,
        limit: number = 100,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/hosts/{host_id}/history',
            path: {
                'host_id': hostId,
            },
            query: {
                'limit': limit,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Restart Host Service
     * Reinicia api ou unbound no agent específico.
     * @param hostId
     * @param service
     * @returns any Successful Response
     * @throws ApiError
     */
    public restartHostServiceApiV1HostsHostIdRestartServicePost(
        hostId: number,
        service: string,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/hosts/{host_id}/restart/{service}',
            path: {
                'host_id': hostId,
                'service': service,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Upgrade Host
     * Dispara self-update no agent pra versão informada.
     * @param hostId
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public upgradeHostApiV1HostsHostIdUpgradePost(
        hostId: number,
        requestBody: UpgradeRequest,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/hosts/{host_id}/upgrade',
            path: {
                'host_id': hostId,
            },
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Push Config
     * Empacota config local (blocklist flags + policies completas) e
     * posta no `/api/v1/host/apply-config` do agent. Retorna o resultado
     * bruto do agent.
     * @param hostId
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public pushConfigApiV1HostsHostIdPushConfigPost(
        hostId: number,
        requestBody: PushConfigRequest,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/hosts/{host_id}/push-config',
            path: {
                'host_id': hostId,
            },
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Batch Push Config
     * Push config pra todos os hosts. Sequencial, falhas isoladas.
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public batchPushConfigApiV1HostsBatchPushConfigPost(
        requestBody: PushConfigRequest,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/hosts/batch/push-config',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
