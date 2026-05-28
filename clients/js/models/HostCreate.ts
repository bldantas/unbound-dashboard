/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
export type HostCreate = {
    label: string;
    /**
     * https://host:port (sem /api/...)
     */
    base_url: string;
    /**
     * Token gerado em Settings → API Tokens do agent
     */
    api_token: string;
    notes?: (string | null);
    /**
     * Org dona do host. None = global (visível a todos os admins).
     */
    org_id?: (number | null);
};

