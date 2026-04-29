import { api } from './client'

export interface DiagRun {
  checks: Array<{ name: string; ok: boolean; detail: string }>
}

export interface PingResult {
  host: string
  reachable: boolean
  avg_ms: number | null
}

export interface DnsResult {
  domain: string
  record_type: string
  answers: string[]
  resolved: boolean
  elapsed_ms: number
}

export const diagnosticsApi = {
  run: () => api.post<DiagRun>('/diagnostics/run'),

  ping: (host: string) =>
    api.post<PingResult>('/diagnostics/ping', { host }),

  dnsLookup: (domain: string, record_type = 'A') =>
    api.post<DnsResult>('/diagnostics/dns', { domain, record_type }),
}
