/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CreateUserRequest } from '../models/CreateUserRequest';
import type { UpdateEmailRequest } from '../models/UpdateEmailRequest';
import type { UpdateRoleRequest } from '../models/UpdateRoleRequest';
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class UsersService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * List Users
     * @returns any Successful Response
     * @throws ApiError
     */
    public listUsersApiV1UsersGet(): CancelablePromise<Array<Record<string, any>>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/users',
        });
    }
    /**
     * Create User
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public createUserApiV1UsersPost(
        requestBody: CreateUserRequest,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/users',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Users Exist
     * Pública — usada por setup.php pra decidir se mostra wizard ou login.
     * Não exige auth porque pré-instalação não tem como autenticar.
     * @returns any Successful Response
     * @throws ApiError
     */
    public usersExistApiV1UsersExistsGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/users/exists',
        });
    }
    /**
     * Update Email
     * @param userId
     * @param requestBody
     * @returns void
     * @throws ApiError
     */
    public updateEmailApiV1UsersUserIdEmailPut(
        userId: number,
        requestBody: UpdateEmailRequest,
    ): CancelablePromise<void> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/users/{user_id}/email',
            path: {
                'user_id': userId,
            },
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Toggle Active
     * @param userId
     * @returns void
     * @throws ApiError
     */
    public toggleActiveApiV1UsersUserIdActivePut(
        userId: number,
    ): CancelablePromise<void> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/users/{user_id}/active',
            path: {
                'user_id': userId,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Delete User
     * @param userId
     * @returns any Successful Response
     * @throws ApiError
     */
    public deleteUserApiV1UsersUserIdDelete(
        userId: number,
    ): CancelablePromise<any> {
        return this.httpRequest.request({
            method: 'DELETE',
            url: '/api/v1/users/{user_id}',
            path: {
                'user_id': userId,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Update Role
     * @param userId
     * @param requestBody
     * @returns void
     * @throws ApiError
     */
    public updateRoleApiV1UsersUserIdRolePut(
        userId: number,
        requestBody: UpdateRoleRequest,
    ): CancelablePromise<void> {
        return this.httpRequest.request({
            method: 'PUT',
            url: '/api/v1/users/{user_id}/role',
            path: {
                'user_id': userId,
            },
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Admin Reset Password
     * Admin gera senha temporária aleatória para o user. Senha é retornada
     * UMA VEZ na resposta (texto plano) — admin deve entregar manualmente
     * ao usuário e este deve trocar no primeiro acesso.
     * @param userId
     * @returns any Successful Response
     * @throws ApiError
     */
    public adminResetPasswordApiV1UsersUserIdPasswordResetPost(
        userId: number,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/users/{user_id}/password-reset',
            path: {
                'user_id': userId,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
