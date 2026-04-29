<template>
  <div class="p-6 space-y-6">
    <h2 class="text-xl font-bold text-gray-800">Saúde do Sistema</h2>

    <!-- Skeleton / loading -->
    <div v-if="loading" class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div
        v-for="i in 6"
        :key="i"
        class="h-24 bg-gray-100 rounded-xl animate-pulse"
      ></div>
    </div>

    <template v-else>
      <!-- Cards de sistema -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <HealthCard label="CPU" :value="snap.cpu_percent" unit="%" :warn="80" :crit="95" icon="🖥️" />
        <HealthCard label="Memória" :value="snap.memory_used_pct" unit="%" :warn="80" :crit="90" icon="💾" />
        <HealthCard label="Disco /" :value="snap.disk_used_pct" unit="%" :warn="80" :crit="90" icon="💿" />
        <HealthCard label="Load avg (1m)" :value="snap.load_1" unit="" :warn="4" :crit="8" icon="⚡" />
      </div>

      <!-- Status Unbound -->
      <div class="bg-white rounded-xl shadow p-4 grid grid-cols-2 md:grid-cols-4 gap-4">
        <div>
          <p class="text-xs text-gray-500 uppercase">Status</p>
          <span
            :class="snap.unbound_running ? 'text-green-600' : 'text-red-600'"
            class="font-bold text-lg"
          >
            {{ snap.unbound_running ? 'Running' : 'Down' }}
          </span>
        </div>
        <div>
          <p class="text-xs text-gray-500 uppercase">Queries totais</p>
          <p class="font-bold text-lg">{{ (snap.total_queries ?? 0).toLocaleString() }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500 uppercase">Bloqueadas</p>
          <p class="font-bold text-lg text-red-600">{{ (snap.blocked_queries ?? 0).toLocaleString() }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500 uppercase">Cache hits</p>
          <p class="font-bold text-lg text-yellow-600">{{ (snap.cache_hits ?? 0).toLocaleString() }}</p>
        </div>
      </div>

      <p class="text-xs text-gray-400">
        Atualizado em {{ updatedAt }}
        <button @click="load(true)" class="ml-2 underline text-blue-500 hover:text-blue-700">Atualizar</button>
      </p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import HealthCard from '@/components/HealthCard.vue'
import { apiClient } from '@/api/client'

interface HealthSnap {
  cpu_percent: number
  memory_used_pct: number
  disk_used_pct: number
  load_1: number
  unbound_running: boolean
  total_queries: number
  blocked_queries: number
  cache_hits: number
}

const loading = ref(true)
const snap = ref<HealthSnap>({} as HealthSnap)
const updatedAt = ref('')

let timer: ReturnType<typeof setInterval> | null = null

async function load(force = false) {
  const { data } = await apiClient.get<HealthSnap>(`/health${force ? '?force=true' : ''}`)
  snap.value = data
  updatedAt.value = new Date().toLocaleTimeString()
  loading.value = false
}

onMounted(() => {
  load()
  timer = setInterval(load, 15_000)
})
onUnmounted(() => { if (timer) clearInterval(timer) })
</script>
