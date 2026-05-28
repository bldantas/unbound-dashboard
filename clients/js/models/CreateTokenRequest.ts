/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
export type CreateTokenRequest = {
    /**
     * Identificação do token, ex: 'master-orchestrator'
     */
    label: string;
    /**
     * Lista de capabilities concedidas. Vazio/None = admin global (backward-compat). Lista não vazia = token restrito a essas caps. Caps válidas: ver /api/v1/api-tokens/capabilities-catalog
     */
    capabilities?: (Array<string> | null);
};

