import { api } from './client'

export interface HealthCheck {
  name: string
  status: 'ok' | 'warn' | 'crit' | 'unknown'
  message: string
  value?: number | string
}

export interface HealthSnap {
  ts: number
  checks: HealthCheck[]
  overall: 'ok' | 'warn' | 'crit'
}

export const healthApi = {
  get: (force = false) =>
    api.get<HealthSnap>('/health', { params: force ? { force: 'true' } : {} }),
}
