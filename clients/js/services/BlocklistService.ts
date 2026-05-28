/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class BlocklistService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Counts
     * Retorna count por categoria (Malware/Adware, Phishing, Judicial).
     * @returns number Successful Response
     * @throws ApiError
     */
    public countsApiV1BlocklistCountsGet(): CancelablePromise<Record<string, number>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/blocklist/counts',
        });
    }
    /**
     * Search
     * Busca paginada em `blocklist_domains` (DuckDB). Substitui o antigo
     * `api/blocklist_search.php`, que lia o arquivo flat e só via ANATEL.
     *
     * Retorna estrutura compatível com o JS atual de `/blocklist.php`:
     * `{success, total, filtered, page, per_page, total_pages, domains, top_tlds, by_category}`.
     * @param q Termo a buscar em domain (LIKE %q%)
     * @param category Filtra por categoria; ausente = todas
     * @param tld Filtra por TLD (sufixo após o último ponto)
     * @param page
     * @param perPage
     * @returns any Successful Response
     * @throws ApiError
     */
    public searchApiV1BlocklistSearchGet(
        q: string = '',
        category?: ('Judicial' | 'Malware/Adware' | 'Phishing' | null),
        tld: string = '',
        page: number = 1,
        perPage: number = 50,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/blocklist/search',
            query: {
                'q': q,
                'category': category,
                'tld': tld,
                'page': page,
                'per_page': perPage,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Clear Category
     * DELETE FROM blocklist_domains WHERE category = ?. Body: {category: str}.
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public clearCategoryApiV1BlocklistClearCategoryPost(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/blocklist/clear-category',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Bulk Insert
     * Bulk UPSERT (legacy shim). Body: [{domain, category, severity}].
     *
     * Pós-V9 mapeia category → primeira source que casa. Novos chamadores devem
     * usar POST /sources/{slug}/sync no lugar.
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public bulkInsertApiV1BlocklistBulkInsertPost(
        requestBody: Array<Record<string, any>>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/blocklist/bulk-insert',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * List Sources
     * Lista todas as sources curadas com flags e estatísticas.
     * @returns any Successful Response
     * @throws ApiError
     */
    public listSourcesApiV1BlocklistSourcesGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/blocklist/sources',
        });
    }
    /**
     * Update Source
     * Toggle index_enabled e/ou block_enabled.
     *
     * Body: {"index_enabled": bool?, "block_enabled": bool?}
     * Se index_enabled=false e count>0, mantém entries (usuário pode reativar
     * depois sem perder dados; pra zerar use POST /sources/{slug}/sync com
     * force depois de desligar, ou DELETE explícito futuramente).
     * @param slug
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public updateSourceApiV1BlocklistSourcesSlugPatch(
        slug: string,
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'PATCH',
            url: '/api/v1/blocklist/sources/{slug}',
            path: {
                'slug': slug,
            },
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Sync Source
     * Dispara sync sob demanda. Retorna {status, count, error}.
     * @param slug
     * @param force Se true, sincroniza mesmo se last_sync recente
     * @returns any Successful Response
     * @throws ApiError
     */
    public syncSourceApiV1BlocklistSourcesSlugSyncPost(
        slug: string,
        force: boolean = true,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/blocklist/sources/{slug}/sync',
            path: {
                'slug': slug,
            },
            query: {
                'force': force,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Domains To Block
     * União dos domínios em sources com block_enabled=true MENOS exceptions.
     *
     * Consumido pelo PHP UnboundConfigManager pra regerar
     * /etc/unbound/includes/blocked_domains.conf. Resposta pode ser pesada
     * (centenas de milhares); não pagina.
     * @returns any Successful Response
     * @throws ApiError
     */
    public domainsToBlockApiV1BlocklistDomainsToBlockGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/blocklist/domains-to-block',
        });
    }
    /**
     * List Exceptions
     * Lista exceções visíveis pro viewer (globais + da própria org).
     * @returns any Successful Response
     * @throws ApiError
     */
    public listExceptionsApiV1BlocklistExceptionsGet(): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/blocklist/exceptions',
        });
    }
    /**
     * Add Exception
     * Adiciona exceção. Body: {"domain": str, "reason": str?, "org_id": int?}.
     *
     * `org_id` opcional. Admin global default = 0 (allowlist global).
     * User org-scoped sempre força a própria org (body.org_id é ignorado/checado).
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public addExceptionApiV1BlocklistExceptionsPost(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/blocklist/exceptions',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Remove Exception
     * Remove exceção. Sem org_id explícito, admin global apaga a global (0);
     * user org-scoped apaga a da própria org.
     * @param domain
     * @param orgId 0=global, N=org. Default = própria org do viewer ou 0 pra admin global.
     * @returns any Successful Response
     * @throws ApiError
     */
    public removeExceptionApiV1BlocklistExceptionsDomainDelete(
        domain: string,
        orgId?: (number | null),
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'DELETE',
            url: '/api/v1/blocklist/exceptions/{domain}',
            path: {
                'domain': domain,
            },
            query: {
                'org_id': orgId,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Bulk Add Exceptions
     * Bulk add. Body: `{"domains": [...], "reason": str?, "org_id": int?}`.
     * Aceita até 50.000 domínios. Pula inválidos (sem ponto, vazio, com espaço) e
     * duplicados (já na tabela ou repetidos no payload).
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public bulkAddExceptionsApiV1BlocklistExceptionsBulkPost(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/blocklist/exceptions/bulk',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Bulk Remove Exceptions
     * Bulk delete. Body: `{"domains": [...], "org_id": int?}`.
     * @param requestBody
     * @returns any Successful Response
     * @throws ApiError
     */
    public bulkRemoveExceptionsApiV1BlocklistExceptionsBulkDeletePost(
        requestBody: Record<string, any>,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'POST',
            url: '/api/v1/blocklist/exceptions/bulk-delete',
            body: requestBody,
            mediaType: 'application/json',
            errors: {
                422: `Validation Error`,
            },
        });
    }
    /**
     * Export Exceptions Csv
     * Download da allowlist em CSV. 1 domínio por linha (compat com import).
     * Inclui scope (global ou nome da org) por linha.
     * @returns any Successful Response
     * @throws ApiError
     */
    public exportExceptionsCsvApiV1BlocklistExceptionsExportCsvGet(): CancelablePromise<any> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/blocklist/exceptions/export.csv',
        });
    }
}
