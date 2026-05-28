/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class HaService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Status
     * @returns any Successful Response
     * @throws ApiError
     */
    public statusApiV1HaStatusGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/ha/status',
        });
    }
    /**
     * List Peers
     * @returns any Successful Response
     * @throws ApiError
     */
    public listPeersApiV1HaPeersGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/ha/peers',
        });
    }
    /**
     * Create Peer
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public createPeerApiV1HaPeersPost(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/ha/peers',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Update Peer
     * @param peerId
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public updatePeerApiV1HaPeersPeerIdPut(
        peerId: number,
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/ha/peers/{peer_id}',
            path: {
                'peer_id': peerId,
            },
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Delete Peer
     * @param peerId
     * @returns any Successful Response
     * @throws ApiError
     */
    public deletePeerApiV1HaPeersPeerIdDelete(
        peerId: number,
    ): CancelablePromise<any> {
        return this.httpRequest.request({
            method: 'DELETE',
            url: '/api/v1/ha/peers/{peer_id}',
            path: {
                'peer_id': peerId,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Set Peer Token
     * Substitui o token de um peer (usado pra "fechar o link" quando
     * ambos os lados foram criados sem coordenar).
     * @param peerId
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public setPeerTokenApiV1HaPeersPeerIdTokenPut(
        peerId: number,
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/ha/peers/{peer_id}/token',
            path: {
                'peer_id': peerId,
            },
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Check Peer
     * @param peerId
     * @returns any Successful Response
     * @throws ApiError
     */
    public checkPeerApiV1HaPeersPeerIdCheckPost(
        peerId: number,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/ha/peers/{peer_id}/check',
            path: {
                'peer_id': peerId,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Manual Failover
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public manualFailoverApiV1HaFailoverPost(
        requestBody: Record<string, any>,
    ): CancelablePromise<any> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/ha/failover',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
