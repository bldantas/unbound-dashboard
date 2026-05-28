/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
export type ApplyRequest = {
    /**
     * Versão semver sem 'v' (ex: 2.17.0) OU sentinel 'latest'
     */
    version: string;
    /**
     * Obrigatório em major bumps
     */
    acknowledge_breaking?: boolean;
};

