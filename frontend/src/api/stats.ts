import { api } from './client'

export interface LiveStats {
  window_hours: number
  total: number
  blocked: number
  resolved: number
  cache_hits: number
  block_rate: number
  top_domains: { domain: string; hits: number }[]
  top_clients: { client_ip: string; hits: number }[]
}

export interface DailyStat {
  date: string
  total: number
  blocked: number
  resolved: number
  cache_hits: number
  block_rate: number
}

export interface QueryLogEntry {
  timestamp: number
  client_ip: string
  domain: string
  query_type: string
  action: string
}

export const statsApi = {
  live: (hours = 24) =>
    api.get<LiveStats>('/stats/live', { params: { hours } }),

  history: (days = 30) =>
    api.get<DailyStat[]>('/stats/history', { params: { days } }),

  logs: (params?: { limit?: number; action?: string; domain?: string }) =>
    api.get<QueryLogEntry[]>('/stats/logs', { params }),
}
