<template>
  <div class="p-6 space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold text-gray-800">
        Alertas
        <span
          v-if="store.unreadCount > 0"
          class="ml-2 bg-red-500 text-white text-xs px-2 py-0.5 rounded-full"
        >
          {{ store.unreadCount }}
        </span>
      </h2>
      <button
        @click="store.markAllRead()"
        class="text-sm text-blue-600 hover:underline"
      >
        Marcar todos como lidos
      </button>
    </div>

    <div class="space-y-2">
      <div
        v-for="alert in store.alerts"
        :key="alert.id"
        :class="[
          'bg-white rounded-xl shadow p-4 flex items-start justify-between gap-4 transition-opacity',
          !alert.is_read ? 'border-l-4' : 'opacity-60',
          alert.severity === 'critical' ? 'border-red-500' :
          alert.severity === 'warning'  ? 'border-yellow-400' : 'border-blue-400',
        ]"
      >
        <div class="space-y-1 min-w-0">
          <div class="flex items-center gap-2">
            <span
              :class="{
                'bg-red-100 text-red-700':    alert.severity === 'critical',
                'bg-yellow-100 text-yellow-700': alert.severity === 'warning',
                'bg-blue-100 text-blue-700':  alert.severity === 'info',
              }"
              class="px-2 py-0.5 rounded-full text-xs font-medium shrink-0"
            >
              {{ alert.severity }}
            </span>
            <p class="font-medium text-gray-800 text-sm truncate">{{ alert.message }}</p>
          </div>
          <p class="text-xs text-gray-400">
            {{ new Date(alert.created_at * 1000).toLocaleString() }}
          </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <button
            v-if="!alert.is_read"
            @click="store.markRead(alert.id)"
            class="text-xs text-gray-400 hover:text-gray-700"
          >
            ✓ Lido
          </button>
          <button
            @click="deleteAlert(alert.id)"
            class="text-xs text-red-400 hover:text-red-600"
            title="Excluir"
          >
            ✕
          </button>
        </div>
      </div>

      <p v-if="!store.alerts.length" class="text-gray-400 text-sm text-center py-8">
        Nenhum alerta encontrado
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useAlertsStore } from '@/stores/alerts'
import { alertsApi } from '@/api/alerts'

const store = useAlertsStore()

async function deleteAlert(id: number) {
  await alertsApi.delete(id)
  await store.fetchAlerts()
  await store.fetchUnreadCount()
}

onMounted(() => {
  store.fetchAlerts()
  store.fetchUnreadCount()
})
</script>
