import { api } from './client'

export interface UpstreamConfig {
  address: string
  port: number
  label: string
  enabled: boolean
}

export interface UpstreamStatus {
  address: string
  port: number
  label: string
  healthy: boolean
  latency_ms: number | null
  checked_at: number | null
}

export interface BalanceStats {
  total_queries: number
  total_cache_hits: number
  cache_hit_ratio: number
}

export const balanceApi = {
  listUpstreams: () =>
    api.get<UpstreamConfig[]>('/balance/upstreams'),

  addUpstream: (upstream: UpstreamConfig) =>
    api.post<{ ok: boolean }>('/balance/upstreams', upstream),

  removeUpstream: (address: string, port: number) =>
    api.delete<{ ok: boolean }>('/balance/upstreams', { params: { address, port } }),

  setEnabled: (address: string, port: number, enabled: boolean) =>
    api.put<{ ok: boolean }>('/balance/upstreams/enabled', { address, port, enabled }),

  checkHealth: () =>
    api.get<UpstreamStatus[]>('/balance/health'),

  getStats: () =>
    api.get<BalanceStats>('/balance/stats'),
}
