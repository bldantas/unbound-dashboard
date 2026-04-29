import { api } from './client'

export interface UnboundStats {
  total: {
    num_queries: number
    num_cachehits: number
    num_cachemiss: number
    num_prefetch: number
    num_zero_ttl: number
    num_recursivereplies: number
    requestlist_avg: number
    requestlist_max: number
    recursion_time_avg: number
    recursion_time_median: number
    tcpusage: number
  }
  time: {
    now: number
    up: number
    elapsed: number
  }
  mem: {
    total_sbrk: number
    cache_rrset: number
    cache_message: number
    mod_iterator: number
    mod_validator: number
    dns64: number
    mod_ipsecmod: number
    streamwait: number
    http_query_buffer: number
    http_response_buffer: number
  }
  num_threads: number
}

export const unboundApi = {
  stats: () => api.get<UnboundStats>('/unbound/stats'),

  status: () => api.get<{ running: boolean; pid: number | null }>('/unbound/status'),

  version: () => api.get<{ version: string }>('/unbound/version'),

  reload: () => api.post<{ ok: boolean; message: string }>('/unbound/reload'),

  flush: (domain: string) =>
    api.post<{ ok: boolean; message: string }>('/unbound/flush', { domain }),

  flushAll: () => api.post<{ ok: boolean; message: string }>('/unbound/flush-all'),
}
