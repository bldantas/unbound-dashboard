import { defineStore } from 'pinia'
import { ref } from 'vue'
import { alertsApi, type Alert } from '@/api/alerts'

export const useAlertsStore = defineStore('alerts', () => {
  const alerts = ref<Alert[]>([])
  const unreadCount = ref(0)

  async function fetchAlerts(unreadOnly = false) {
    const { data } = await alertsApi.list(unreadOnly)
    alerts.value = data
  }

  async function fetchUnreadCount() {
    const { data } = await alertsApi.countUnread()
    unreadCount.value = data.unread
  }

  async function markRead(id: number) {
    await alertsApi.markRead(id)
    const alert = alerts.value.find((a) => a.id === id)
    if (alert) alert.is_read = true
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  }

  async function markAllRead() {
    await alertsApi.markAllRead()
    alerts.value.forEach((a) => (a.is_read = true))
    unreadCount.value = 0
  }

  return { alerts, unreadCount, fetchAlerts, fetchUnreadCount, markRead, markAllRead }
})
