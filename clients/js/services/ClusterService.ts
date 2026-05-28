/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class ClusterService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Peer Ping
     * Healthcheck autenticado entre peers HA.
     *
     * Validado contra ha_peers.api_token_hash (bcrypt). Retorna info do
     * servidor pra o peer chamador saber com quem está falando.
     * @param xApiToken
     * @param authorization
     * @returns any Successful Response
     * @throws ApiError
     */
    public peerPingApiV1ClusterPeerPingGet(
        xApiToken?: (string | null),
        authorization?: (string | null),
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/cluster/peer-ping',
            headers: {
                'X-Api-Token': xApiToken,
                'authorization': authorization,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
