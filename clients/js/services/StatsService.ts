/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { CancelablePromise } from '../core/CancelablePromise';
import type { BaseHttpRequest } from '../core/BaseHttpRequest';
export class StatsService {
    constructor(public readonly httpRequest: BaseHttpRequest) {}
    /**
     * Summary
     * Sumário de DNS na última janela: totais (total/blocked/resolved + block_rate),
     * top domínios bloqueados e top clientes por volume.
     *
     * Lê do DuckDB em `/var/lib/unbound-dashboard/unbound_dash.duckdb` — snapshot
     * populado por `tools/migrate_mariadb_to_duckdb.py`. Quando o worker
     * `log_watcher.py` estiver ativo, será atualizado em tempo real.
     * @param windowHours Janela retroativa em horas (1-720)
     * @param topN Quantidade de top domínios/clientes
     * @returns any Successful Response
     * @throws ApiError
     */
    public summaryApiV1StatsSummaryGet(
        windowHours: number = 24,
        topN: number = 10,
    ): CancelablePromise<Record<string, any>> {
        return this.httpRequest.request({
            method: 'GET',
            url: '/api/v1/stats/summary',
            query: {
                'window_hours': windowHours,
                'top_n': topN,
            },
            errors: {
                422: `Validation Error`,
            },
        });
    }
}
