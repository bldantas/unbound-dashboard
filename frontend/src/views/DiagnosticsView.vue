<template>
  <div class="p-6 space-y-6">
    <h2 class="text-xl font-bold text-gray-800">Diagnósticos</h2>

    <!-- Bateria completa -->
    <div class="bg-white rounded-xl shadow p-4 space-y-3">
      <div class="flex items-center gap-3">
        <button
          @click="runAll"
          :disabled="running"
          class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition"
        >
          {{ running ? 'Executando…' : 'Executar bateria completa' }}
        </button>
      </div>

      <pre v-if="allResult" class="bg-gray-50 rounded p-3 text-xs font-mono overflow-auto max-h-60">{{ allResult }}</pre>
    </div>

    <!-- Ping -->
    <div class="bg-white rounded-xl shadow p-4 space-y-3">
      <h3 class="font-semibold text-gray-700">Ping</h3>
      <div class="flex gap-2">
        <input v-model="pingHost" placeholder="ex: 8.8.8.8" class="border rounded px-2 py-1 text-sm flex-1" />
        <button @click="runPing" class="bg-indigo-600 text-white text-sm px-3 py-1 rounded hover:bg-indigo-700">
          Testar
        </button>
      </div>
      <pre v-if="pingResult" class="bg-gray-50 rounded p-3 text-xs font-mono overflow-auto">{{ pingResult }}</pre>
    </div>

    <!-- DNS Resolve -->
    <div class="bg-white rounded-xl shadow p-4 space-y-3">
      <h3 class="font-semibold text-gray-700">Resolução DNS</h3>
      <div class="flex gap-2 flex-wrap">
        <input v-model="dnsHost" placeholder="ex: example.com" class="border rounded px-2 py-1 text-sm flex-1" />
        <input v-model="dnsServer" placeholder="servidor (127.0.0.1)" class="border rounded px-2 py-1 text-sm w-40" />
        <button @click="runDns" class="bg-indigo-600 text-white text-sm px-3 py-1 rounded hover:bg-indigo-700">
          Resolver
        </button>
      </div>
      <pre v-if="dnsResult" class="bg-gray-50 rounded p-3 text-xs font-mono overflow-auto">{{ dnsResult }}</pre>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { apiClient } from '@/api/client'

const running = ref(false)
const allResult = ref('')
const pingHost = ref('8.8.8.8')
const pingResult = ref('')
const dnsHost = ref('example.com')
const dnsServer = ref('127.0.0.1')
const dnsResult = ref('')

async function runAll() {
  running.value = true
  allResult.value = ''
  try {
    const { data } = await apiClient.post('/diagnostics/run')
    allResult.value = JSON.stringify(data, null, 2)
  } finally {
    running.value = false
  }
}

async function runPing() {
  const { data } = await apiClient.post('/diagnostics/ping', { host: pingHost.value })
  pingResult.value = JSON.stringify(data, null, 2)
}

async function runDns() {
  const { data } = await apiClient.post('/diagnostics/dns', {
    domain: dnsHost.value,
    server: dnsServer.value,
  })
  dnsResult.value = JSON.stringify(data, null, 2)
}
</script>
