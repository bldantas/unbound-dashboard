<template>
  <div class="p-6 space-y-6">
    <h2 class="text-xl font-bold text-gray-800">Blocklist</h2>

    <!-- Fontes -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b">
        <h3 class="font-semibold text-gray-700">Fontes</h3>
        <button
          @click="syncAll"
          :disabled="syncing"
          class="bg-blue-600 text-white text-sm px-3 py-1 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition"
        >
          {{ syncing ? 'Sincronizando…' : 'Sincronizar tudo' }}
        </button>
      </div>

      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
          <tr>
            <th class="px-4 py-2 text-left">Nome</th>
            <th class="px-4 py-2 text-left">URL</th>
            <th class="px-4 py-2 text-right">Ação</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="src in sources" :key="src.url" class="border-t border-gray-100 hover:bg-gray-50">
            <td class="px-4 py-2">{{ src.name }}</td>
            <td class="px-4 py-2 text-xs text-gray-500 truncate max-w-xs">{{ src.url }}</td>
            <td class="px-4 py-2 text-right">
              <button @click="removeSource(src.url)" class="text-red-500 hover:underline text-xs">Remover</button>
            </td>
          </tr>
          <tr v-if="!sources.length">
            <td colspan="3" class="px-4 py-4 text-center text-gray-400 text-sm">Nenhuma fonte configurada</td>
          </tr>
        </tbody>
      </table>

      <!-- Adicionar fonte -->
      <div class="px-4 py-3 border-t bg-gray-50 flex gap-2 flex-wrap">
        <input v-model="newName" placeholder="Nome" class="border rounded px-2 py-1 text-sm w-32" />
        <input v-model="newUrl" placeholder="URL" class="border rounded px-2 py-1 text-sm flex-1" />
        <button @click="addSource" class="bg-green-600 text-white text-sm px-3 py-1 rounded hover:bg-green-700">
          Adicionar
        </button>
      </div>
    </div>

    <!-- Domínios personalizados -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <div class="px-4 py-3 border-b">
        <h3 class="font-semibold text-gray-700">Domínios bloqueados manualmente</h3>
      </div>

      <div class="px-4 py-3 flex gap-2">
        <input v-model="newDomain" placeholder="ex: ads.example.com" class="border rounded px-2 py-1 text-sm flex-1" @keyup.enter="blockDomain" />
        <button @click="blockDomain" class="bg-red-600 text-white text-sm px-3 py-1 rounded hover:bg-red-700">
          Bloquear
        </button>
      </div>

      <ul class="divide-y max-h-60 overflow-y-auto">
        <li v-for="d in domains" :key="d" class="flex items-center justify-between px-4 py-1 hover:bg-gray-50 text-sm">
          <span class="font-mono text-xs">{{ d }}</span>
          <button @click="unblockDomain(d)" class="text-red-500 hover:underline text-xs">Remover</button>
        </li>
        <li v-if="!domains.length" class="px-4 py-4 text-center text-gray-400 text-sm">
          Nenhum domínio bloqueado manualmente
        </li>
      </ul>
    </div>

    <p v-if="syncResult" class="text-sm text-green-700 bg-green-50 rounded px-3 py-2">{{ syncResult }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { apiClient } from '@/api/client'

interface Source { name: string; url: string }

const sources = ref<Source[]>([])
const domains = ref<string[]>([])
const newName = ref('')
const newUrl = ref('')
const newDomain = ref('')
const syncing = ref(false)
const syncResult = ref('')

async function loadSources() {
  const { data } = await apiClient.get<Source[]>('/blocklist/sources')
  sources.value = data
}

async function loadDomains() {
  const { data } = await apiClient.get<string[]>('/blocklist/domains')
  domains.value = data
}

async function addSource() {
  if (!newName.value || !newUrl.value) return
  await apiClient.post('/blocklist/sources', { name: newName.value, url: newUrl.value })
  newName.value = ''
  newUrl.value = ''
  await loadSources()
}

async function removeSource(url: string) {
  await apiClient.delete('/blocklist/sources', { params: { url } })
  await loadSources()
}

async function syncAll() {
  syncing.value = true
  syncResult.value = ''
  try {
    const { data } = await apiClient.post('/blocklist/sync')
    syncResult.value = `Sync concluído: ${data.added ?? 0} domínios adicionados`
  } finally {
    syncing.value = false
  }
}

async function blockDomain() {
  if (!newDomain.value.trim()) return
  await apiClient.post('/blocklist/domains', { domain: newDomain.value.trim() })
  newDomain.value = ''
  await loadDomains()
}

async function unblockDomain(domain: string) {
  await apiClient.delete(`/blocklist/domains/${encodeURIComponent(domain)}`)
  await loadDomains()
}

onMounted(() => {
  loadSources()
  loadDomains()
})
</script>
