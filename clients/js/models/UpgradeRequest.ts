/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
export type UpgradeRequest = {
    /**
     * Semver sem 'v' (ex: 2.21.4) OU sentinel 'latest' (cada agent resolve via seu próprio /updates/check — evita race entre caches de master/agent).
     */
    version: string;
};

