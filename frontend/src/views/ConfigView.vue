<template>
  <div class="p-6 space-y-6">
    <h2 class="text-xl font-bold text-gray-800">Configuração Unbound</h2>

    <!-- Status + versão -->
    <div class="bg-white rounded-xl shadow p-4 flex items-center gap-6">
      <div>
        <p class="text-xs text-gray-500 uppercase">Status</p>
        <span :class="status.running ? 'text-green-600' : 'text-red-600'" class="font-bold text-lg">
          {{ status.running ? 'Running' : 'Stopped' }}
        </span>
      </div>
      <div>
        <p class="text-xs text-gray-500 uppercase">Versão</p>
        <p class="font-mono text-sm">{{ version }}</p>
      </div>
    </div>

    <!-- Ações -->
    <div class="flex flex-wrap gap-3">
      <button
        @click="reload"
        class="bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 transition"
      >
        Reload config
      </button>
      <button
        @click="showFlush = !showFlush"
        class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition"
      >
        Flush domínio
      </button>
      <button
        @click="flushAll"
        class="bg-red-100 text-red-700 px-4 py-2 rounded-lg hover:bg-red-200 transition"
      >
        Flush cache completo
      </button>
    </div>

    <div v-if="showFlush" class="flex gap-2">
      <input
        v-model="flushDomain"
        placeholder="ex: ads.example.com"
        class="border rounded px-2 py-1 text-sm flex-1"
      />
      <button @click="flushOne" class="bg-red-600 text-white text-sm px-3 py-1 rounded hover:bg-red-700">
        Flush
      </button>
    </div>

    <p v-if="message" class="text-sm rounded px-3 py-2" :class="messageClass">{{ message }}</p>

    <!-- Stats -->
    <div v-if="stats" class="bg-white rounded-xl shadow p-4">
      <h3 class="font-semibold text-gray-700 mb-2">Estatísticas Unbound</h3>
      <pre class="text-xs font-mono bg-gray-50 p-3 rounded overflow-auto max-h-60">{{ JSON.stringify(stats, null, 2) }}</pre>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { apiClient } from '@/api/client'

const status = ref<{ running: boolean }>({ running: false })
const version = ref('…')
const stats = ref<Record<string, unknown> | null>(null)
const message = ref('')
const messageClass = ref('bg-green-50 text-green-700')
const showFlush = ref(false)
const flushDomain = ref('')

function notify(text: string, ok = true) {
  message.value = text
  messageClass.value = ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'
  setTimeout(() => { message.value = '' }, 4000)
}

async function load() {
  const [s, v, st] = await Promise.all([
    apiClient.get<{ running: boolean }>('/unbound/status'),
    apiClient.get<{ version: string }>('/unbound/version'),
    apiClient.get<Record<string, unknown>>('/unbound/stats'),
  ])
  status.value = s.data
  version.value = v.data.version ?? '?'
  stats.value = st.data
}

async function reload() {
  try {
    await apiClient.post('/unbound/reload')
    notify('Config recarregada com sucesso')
    await load()
  } catch {
    notify('Erro ao recarregar config', false)
  }
}

async function flushOne() {
  if (!flushDomain.value.trim()) return
  await apiClient.post('/unbound/flush', { domain: flushDomain.value.trim() })
  notify(`Cache para ${flushDomain.value} limpo`)
  flushDomain.value = ''
}

async function flushAll() {
  await apiClient.post('/unbound/flush-all')
  notify('Cache completo limpo')
}

onMounted(load)
</script>
