/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { ChangePasswordRequest } from '../models/ChangePasswordRequest';
import type { Login2FARequest } from '../models/Login2FARequest';
import type { LoginRequest } from '../models/LoginRequest';
import type { PasswordResetConfirm } from '../models/PasswordResetConfirm';
import type { PasswordResetRequest } from '../models/PasswordResetRequest';
import type { TokenResponse } from '../models/TokenResponse';
import type { TOTPConfirmRequest } from '../models/TOTPConfirmRequest';
import type { TOTPDisableRequest } from '../models/TOTPDisableRequest';
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class AuthService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Login
     * Retorna TokenResponse normal OU `{requires_totp: true, challenge_token}`
     * se o user tem 2FA habilitado. Frontend (login.php) precisa detectar
     * `requires_totp` e redirecionar pro fluxo de 2FA.
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public loginApiV1AuthLoginPost(
        requestBody: LoginRequest,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/auth/login',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Login 2Fa Verify
     * Segundo passo do login pra users com 2FA habilitado. Recebe o
     * challenge_token vindo de /login e o code TOTP atual.
     * @param requestBody
     * @returns TokenResponse Successful Response
     * @throws ApiError
     */
    public login2FaVerifyApiV1AuthLogin2FaVerifyPost(
        requestBody: Login2FARequest,
    ): CancelablePromise<TokenResponse> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/auth/login/2fa-verify',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Me
     * @returns any Successful Response
     * @throws ApiError
     */
    public meApiV1AuthMeGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/auth/me',
        });
    }
    /**
     * Refresh
     * Renova o JWT do user. Aceita o JWT atual (ainda válido OU expirado
     * nos últimos `_REFRESH_GRACE_MINUTES`) e retorna um novo com TTL
     * completo. Útil pra sliding session — frontend chama proativamente
     * quando o JWT está prestes a expirar.
     *
     * Segurança: como aceita JWT expirado por até N min, atacante que
     * rouba JWT consegue renovar dentro dessa janela. Mantemos grace
     * curto (10min) pra minimizar. Revogação real precisa de denylist
     * em Redis (fora de escopo aqui).
     *
     * Validações:
     * - Conta ainda existe + ativa (não-bloqueada). Se admin desativar
     * o user, o JWT velho não consegue renovar mais.
     * @param authorization
     * @returns TokenResponse Successful Response
     * @throws ApiError
     */
    public refreshApiV1AuthRefreshPost(
        authorization?: (string | null),
    ): CancelablePromise<TokenResponse> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/auth/refresh',
            headers: {
                'authorization': authorization,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * List My Sessions
     * Lista sessões ativas (Redis tracking) do user autenticado.
     * Admin pode passar `?all=1` pra listar todas as sessões do sistema.
     * @returns any Successful Response
     * @throws ApiError
     */
    public listMySessionsApiV1AuthSessionsGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/auth/sessions',
        });
    }
    /**
     * Revoke My Session
     * Revoga uma sessão específica (logout cirúrgico). User só pode revogar
     * suas próprias sessões; admin pode revogar de qualquer.
     * @param tokenHash
     * @returns void
     * @throws ApiError
     */
    public revokeMySessionApiV1AuthSessionsTokenHashDelete(
        tokenHash: string,
    ): CancelablePromise<void> {
        return this.httpRequest.request({
            method: 'DELETE',
            url: '/api/v1/auth/sessions/{token_hash}',
            path: {
                'token_hash': tokenHash,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Revoke User
     * Force-revoke todos os tokens emitidos pra `user_id` até este momento.
     *
     * Permissões:
     * - Admin pode revogar qualquer user
     * - User pode revogar a SI MESMO (auto-logout-everywhere)
     * @param userId
     * @returns any Successful Response
     * @throws ApiError
     */
    public revokeUserApiV1AuthRevokeUserIdPost(
        userId: number,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/auth/revoke/{user_id}',
            path: {
                'user_id': userId,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Logout
     * JWT é stateless — cliente apenas descarta o token localmente.
     * Pra invalidação real (revogação imediata pré-expiração), implementar
     * denylist em Redis com TTL = exp do JWT. Hoje fora de escopo.
     * @returns void
     * @throws ApiError
     */
    public logoutApiV1AuthLogoutPost(): CancelablePromise<void> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/auth/logout',
        });
    }
    /**
     * Change Password
     * Altera a senha do usuário autenticado. Chamado pelo PHP Auth::updatePassword
     * para sincronizar DuckDB com o novo hash do MariaDB durante a transição.
     * @param requestBody
     * @returns void
     * @throws ApiError
     */
    public changePasswordApiV1AuthMePasswordPut(
        requestBody: ChangePasswordRequest,
    ): CancelablePromise<void> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/auth/me/password',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Request Password Reset
     * Gera token de reset se email pertence a user ativo. Retorna o token (cru)
     * pra o caller (PHP) enviar por email — Python NÃO envia email diretamente.
     * Resposta sempre 200 (timing-safe; não revela se email existe).
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public requestPasswordResetApiV1AuthPasswordResetRequestPost(
        requestBody: PasswordResetRequest,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/auth/password-reset/request',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Confirm Password Reset
     * @param requestBody
     * @returns void
     * @throws ApiError
     */
    public confirmPasswordResetApiV1AuthPasswordResetConfirmPost(
        requestBody: PasswordResetConfirm,
    ): CancelablePromise<void> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/auth/password-reset/confirm',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Setup 2Fa
     * Gera secret novo + URI provisionamento. NÃO persiste — user precisa
     * confirmar com code via /2fa/confirm pra ativar de fato.
     * @returns any Successful Response
     * @throws ApiError
     */
    public setup2FaApiV1Auth2FaSetupPost(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/auth/2fa/setup',
        });
    }
    /**
     * Confirm 2Fa
     * Valida code do secret novo + persiste no user.
     * @param requestBody
     * @returns void
     * @throws ApiError
     */
    public confirm2FaApiV1Auth2FaConfirmPost(
        requestBody: TOTPConfirmRequest,
    ): CancelablePromise<void> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/auth/2fa/confirm',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Disable 2Fa
     * User desabilita o próprio 2FA. Exige code TOTP válido (anti-takeover).
     * @param requestBody
     * @returns void
     * @throws ApiError
     */
    public disable2FaApiV1Auth2FaDisablePost(
        requestBody: TOTPDisableRequest,
    ): CancelablePromise<void> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/auth/2fa/disable',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Admin Reset 2Fa
     * Admin zera 2FA de um user (caso de celular perdido). Requer
     * `users.manage`. Self-target permitido como fallback (admin que perdeu
     * o próprio celular E é o único admin — sem isso fica trancado).
     * @param userId
     * @returns void
     * @throws ApiError
     */
    public adminReset2FaApiV1Auth2FaAdminResetUserIdPost(
        userId: number,
    ): CancelablePromise<void> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/auth/2fa/admin-reset/{user_id}',
            path: {
                'user_id': userId,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
