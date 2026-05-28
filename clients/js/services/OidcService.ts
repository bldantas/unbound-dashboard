/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class OidcService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Public Info
     * Sem auth — login.php usa pra decidir se mostra botão 'Entrar com SSO'.
     * @returns any Successful Response
     * @throws ApiError
     */
    public publicInfoApiV1AuthOidcPublicInfoGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/auth/oidc/public-info',
        });
    }
    /**
     * Get Config
     * @returns any Successful Response
     * @throws ApiError
     */
    public getConfigApiV1AuthOidcConfigGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/auth/oidc/config',
        });
    }
    /**
     * Update Config
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public updateConfigApiV1AuthOidcConfigPut(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/auth/oidc/config',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Probe Issuer
     * Valida um issuer URL: fetch do `.well-known/openid-configuration`
     * + JWKS (best-effort). Retorna metadata descoberto pra UI mostrar e
     * pra admin confirmar antes de salvar.
     *
     * Não persiste nada — só faz GETs HTTP. Não exige scopes/client_id.
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public probeIssuerApiV1AuthOidcProbePost(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/auth/oidc/probe',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Oidc Login
     * Sem auth — quem chega aqui ainda não fez login. Redireciona pro IdP.
     * @returns any Successful Response
     * @throws ApiError
     */
    public oidcLoginApiV1AuthOidcLoginGet(): CancelablePromise<any> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/auth/oidc/login',
        });
    }
    /**
     * Oidc Callback
     * IdP redirecionou pra cá com ?code=&state=. Troca por tokens + cria sessão.
     * @param code
     * @param state
     * @param error
     * @returns any Successful Response
     * @throws ApiError
     */
    public oidcCallbackApiV1AuthOidcCallbackGet(
        code: string,
        state: string,
        error?: (string | null),
    ): CancelablePromise<any> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/auth/oidc/callback',
            query: {
                'code': code,
                'state': state,
                'error': error,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
