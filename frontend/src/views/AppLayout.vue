<template>
  <div class="min-h-screen bg-gray-100 flex">
    <!-- Sidebar -->
    <aside class="w-56 bg-gray-900 text-white flex flex-col">
      <div class="px-6 py-5 font-bold text-lg border-b border-gray-700">Unbound v2</div>
      <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm hover:bg-gray-700 transition"
          active-class="bg-gray-700 font-semibold"
        >
          {{ item.label }}
          <span
            v-if="item.badge && item.badge > 0"
            class="ml-auto bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full"
          >
            {{ item.badge }}
          </span>
        </RouterLink>
      </nav>
      <div class="px-4 py-4 border-t border-gray-700 text-xs text-gray-400 space-y-1">
        <div class="truncate">{{ auth.username }}</div>
        <div class="text-gray-600 capitalize">{{ auth.role }}</div>
        <button @click="handleLogout" class="text-red-400 hover:text-red-300 transition mt-1">
          Sair
        </button>
      </div>
    </aside>

    <!-- Content -->
    <main class="flex-1 overflow-auto">
      <RouterView />
    </main>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAlertsStore } from '@/stores/alerts'

const auth = useAuthStore()
const alertsStore = useAlertsStore()
const router = useRouter()

// Atualiza badge de alertas a cada 60 s
let alertsTimer: ReturnType<typeof setInterval>
onMounted(() => {
  alertsStore.fetchUnreadCount()
  alertsTimer = setInterval(() => alertsStore.fetchUnreadCount(), 60_000)
})
onUnmounted(() => clearInterval(alertsTimer))

const navItems = computed(() => {
  const items = [
    { to: '/', label: '📊 Dashboard', badge: 0 },
    { to: '/logs', label: '📝 Logs DNS', badge: 0 },
    { to: '/history', label: '📈 Histórico', badge: 0 },
    { to: '/health', label: '🏥 Saúde', badge: 0 },
    { to: '/alerts', label: '🔔 Alertas', badge: alertsStore.unreadCount },
    { to: '/changelog', label: '📋 Changelog', badge: 0 },
  ]
  if (auth.isAdmin) {
    items.push(
      { to: '/blocklist', label: '🚫 Blocklist', badge: 0 },
      { to: '/config', label: '⚙️ Unbound', badge: 0 },
      { to: '/diagnostics', label: '🔍 Diagnósticos', badge: 0 },
      { to: '/balance', label: '⚖️ Balanceamento', badge: 0 },
      { to: '/settings', label: '🛠️ Configurações', badge: 0 },
    )
  }
  return items
})

async function handleLogout() {
  auth.logout()
  router.push('/login')
}
</script>
