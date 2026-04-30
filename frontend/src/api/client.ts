import axios, { type AxiosError } from 'axios'
import { useAuthStore } from '@/stores/auth'

export const api = axios.create({
  baseURL: '/api/v2',
  headers: { 'Content-Type': 'application/json' },
  timeout: 30_000,
})

// Injeta Bearer token e X-Request-ID em cada request
api.interceptors.request.use((config) => {
  const auth = useAuthStore()
  if (auth.token) {
    config.headers.Authorization = `Bearer ${auth.token}`
  }
  // Propaga X-Request-ID do servidor quando presente, ou gera um novo
  config.headers['X-Request-ID'] = crypto.randomUUID()
  return config
})

// Trata erros globais: 401 → logout, 429 → avisa, 5xx → loga
api.interceptors.response.use(
  (res) => res,
  (err: AxiosError) => {
    const status = err.response?.status

    if (status === 401) {
      const auth = useAuthStore()
      auth.logout()
      window.location.href = '/login'
      return Promise.reject(err)
    }

    if (status === 429) {
      // Dispara evento global para toasts
      window.dispatchEvent(
        new CustomEvent('api:error', {
          detail: { message: 'Muitas requisições. Aguarde um momento.', type: 'warning' },
        })
      )
    }

    if (status && status >= 500) {
      window.dispatchEvent(
        new CustomEvent('api:error', {
          detail: { message: 'Erro interno do servidor.', type: 'error' },
        })
      )
    }

    return Promise.reject(err)
  }
)

// Alias para compatibilidade — views criadas usam apiClient
export const apiClient = api
