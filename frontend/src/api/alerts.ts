import { api } from './client'

export interface Alert {
  id: number
  severity: 'info' | 'warning' | 'critical'
  message: string
  is_read: boolean
  created_at: number  // Unix timestamp
}

export const alertsApi = {
  list: (unreadOnly = false) =>
    api.get<Alert[]>('/alerts', { params: { unread_only: unreadOnly } }),

  countUnread: () =>
    api.get<{ unread: number }>('/alerts/count'),

  markRead: (id: number) =>
    api.post(`/alerts/${id}/read`),

  markAllRead: () =>
    api.post('/alerts/read-all'),

  delete: (id: number) =>
    api.delete(`/alerts/${id}`),
}
