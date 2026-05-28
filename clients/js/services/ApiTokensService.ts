/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CreateTokenRequest } from '../models/CreateTokenRequest';
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class ApiTokensService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * List Tokens
     * @param includeRevoked
     * @returns any Successful Response
     * @throws ApiError
     */
    public listTokensApiV1ApiTokensGet(
        includeRevoked: boolean = false,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/api-tokens',
            query: {
                'include_revoked': includeRevoked,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Create Token
     * Cria token novo. Retorna o **raw_token** UMA VEZ — admin tem que copiar
     * agora. Subsequentes calls só mostram o hash + metadata.
     *
     * `capabilities` (v2.110+):
     * - Omitido/null/[] → admin global (sem restrições)
     * - Lista de strings → token só pode chamar endpoints que pedem essas caps
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public createTokenApiV1ApiTokensPost(
        requestBody: CreateTokenRequest,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/api-tokens',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * List Capabilities
     * Lista capabilities disponíveis pra atribuir a tokens.
     *
     * Usado pela UI pra montar checkboxes na criação de token escopado.
     * @returns any Successful Response
     * @throws ApiError
     */
    public listCapabilitiesApiV1ApiTokensCapabilitiesCatalogGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/api-tokens/capabilities-catalog',
        });
    }
    /**
     * Revoke Token
     * @param tokenId
     * @returns void
     * @throws ApiError
     */
    public revokeTokenApiV1ApiTokensTokenIdDelete(
        tokenId: number,
    ): CancelablePromise<void> {
        return this.httpRequest.request({
            method: 'DELETE',
            url: '/api/v1/api-tokens/{token_id}',
            path: {
                'token_id': tokenId,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
