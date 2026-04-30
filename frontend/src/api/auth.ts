import { api } from './client'

export interface LoginResponse {
  access_token: string
  token_type: string
  role: string
}

export interface MeResponse {
  id: number
  username: string
  role: string
  email: string | null
  is_active: boolean
}

export const authApi = {
  login: (username: string, password: string) =>
    api.post<LoginResponse>('/auth/login', { username, password }),

  me: () => api.get<MeResponse>('/auth/me'),

  logout: () => api.post('/auth/logout'),
}
