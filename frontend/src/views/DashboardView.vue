<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold text-gray-800">Dashboard</h2>
      <div class="flex items-center gap-3 text-sm text-gray-400">
        <span v-if="lastUpdated">Atualizado às {{ lastUpdated }}</span>
        <button
          @click="load"
          :disabled="loading"
          class="text-blue-600 hover:underline disabled:opacity-40"
        >
          ↻ Atualizar
        </button>
      </div>
    </div>

    <div v-if="error" class="bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm">
      {{ error }}
    </div>

    <div v-if="loading && !stats" class="text-gray-500">Carregando…</div>

    <template v-if="stats">
      <!-- Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Total (24h)" :value="stats.total.toLocaleString()" color="blue" />
        <StatCard label="Bloqueadas" :value="stats.blocked.toLocaleString()" color="red" />
        <StatCard label="Resolvidas" :value="stats.resolved.toLocaleString()" color="green" />
        <StatCard label="Cache hits" :value="stats.cache_hits.toLocaleString()" color="yellow" />
      </div>

      <!-- Taxa de bloqueio com barra de progresso -->
      <div class="bg-white rounded-xl shadow p-4 space-y-2">
        <div class="flex items-center justify-between">
          <p class="text-sm text-gray-500">Taxa de bloqueio</p>
          <p class="text-2xl font-bold text-red-600">{{ stats.block_rate }}%</p>
        </div>
        <div class="w-full bg-gray-100 rounded-full h-2">
          <div
            class="bg-red-500 h-2 rounded-full transition-all duration-500"
            :style="{ width: `${Math.min(stats.block_rate, 100)}%` }"
          />
        </div>
      </div>

      <!-- Top domínios -->
      <div class="bg-white rounded-xl shadow p-4">
        <h3 class="font-semibold text-gray-700 mb-3">Top domínios consultados</h3>
        <ul class="space-y-1">
          <li
            v-for="d in stats.top_domains"
            :key="d.domain"
            class="flex justify-between text-sm text-gray-600"
          >
            <span class="truncate">{{ d.domain }}</span>
            <span class="font-mono ml-4 shrink-0">{{ d.hits }}</span>
          </li>
          <li v-if="!stats.top_domains.length" class="text-gray-400 text-sm">Sem dados</li>
        </ul>
      </div>

      <!-- Top clientes -->
      <div class="bg-white rounded-xl shadow p-4">
        <h3 class="font-semibold text-gray-700 mb-3">Top clientes</h3>
        <ul class="space-y-1">
          <li
            v-for="c in stats.top_clients"
            :key="c.client_ip"
            class="flex justify-between text-sm text-gray-600"
          >
            <span class="font-mono">{{ c.client_ip }}</span>
            <span class="font-mono ml-4 shrink-0">{{ c.hits }}</span>
          </li>
          <li v-if="!stats.top_clients.length" class="text-gray-400 text-sm">Sem dados</li>
        </ul>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useIntervalFn } from '@vueuse/core'
import { statsApi, type LiveStats } from '@/api/stats'
import StatCard from '@/components/StatCard.vue'

const stats = ref<LiveStats | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const lastUpdated = ref<string | null>(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await statsApi.live(24)
    stats.value = data
    lastUpdated.value = new Date().toLocaleTimeString()
  } catch {
    error.value = 'Não foi possível carregar as estatísticas.'
  } finally {
    loading.value = false
  }
}

// Auto-refresh a cada 30 segundos
const { pause, resume } = useIntervalFn(load, 30_000, { immediate: false })

onMounted(() => {
  load()
  resume()
})

onUnmounted(() => {
  pause()
})
</script>
