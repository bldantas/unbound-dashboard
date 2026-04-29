<template>
  <div class="p-6 space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
      <div class="flex items-center gap-2">
        <h2 class="text-xl font-bold text-gray-800">Logs DNS</h2>
        <!-- Indicador WebSocket -->
        <span
          :class="wsStatus === 'OPEN' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
          class="px-2 py-0.5 rounded-full text-xs font-medium"
        >
          {{ wsStatus === 'OPEN' ? '● live' : '○ offline' }}
        </span>
      </div>

      <!-- Controles de filtro (histórico) -->
      <div class="flex gap-2">
        <input
          v-model="domainFilter"
          placeholder="Filtrar por domínio…"
          class="border border-gray-300 rounded-lg px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          @keyup.enter="fetchLogs"
        />
        <select
          v-model="actionFilter"
          class="border border-gray-300 rounded-lg px-3 py-1 text-sm"
          @change="fetchLogs"
        >
          <option value="">Todas as ações</option>
          <option value="resolved">resolved</option>
          <option value="blocked">blocked</option>
          <option value="cached">cached</option>
        </select>
        <button
          @click="mode = mode === 'live' ? 'history' : 'live'"
          class="bg-blue-600 text-white px-3 py-1 rounded-lg text-sm hover:bg-blue-700 transition"
        >
          {{ mode === 'live' ? 'Ver histórico' : 'Ver live' }}
        </button>
      </div>
    </div>

    <!-- Tabela -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
          <tr>
            <th class="px-4 py-2 text-left">Hora</th>
            <th class="px-4 py-2 text-left">Cliente</th>
            <th class="px-4 py-2 text-left">Domínio</th>
            <th class="px-4 py-2 text-left">Tipo</th>
            <th class="px-4 py-2 text-left">Ação</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(log, i) in displayedLogs"
            :key="`${log.timestamp}-${log.domain}-${i}`"
            class="border-t border-gray-100 hover:bg-gray-50"
          >
            <td class="px-4 py-2 font-mono text-xs text-gray-500">
              {{ new Date(log.timestamp * 1000).toLocaleTimeString() }}
            </td>
            <td class="px-4 py-2 font-mono text-xs">{{ log.client_ip }}</td>
            <td class="px-4 py-2 truncate max-w-xs">{{ log.domain }}</td>
            <td class="px-4 py-2 text-xs text-gray-500">{{ log.query_type }}</td>
            <td class="px-4 py-2">
              <span
                :class="{
                  'bg-red-100 text-red-700': log.action === 'blocked',
                  'bg-green-100 text-green-700': log.action === 'resolved',
                  'bg-yellow-100 text-yellow-700': log.action === 'cached',
                }"
                class="px-2 py-0.5 rounded-full text-xs font-medium"
              >
                {{ log.action }}
              </span>
            </td>
          </tr>
          <tr v-if="!displayedLogs.length">
            <td colspan="5" class="px-4 py-6 text-center text-gray-400">Sem registros</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { statsApi, type QueryLogEntry } from '@/api/stats'
import { useLiveLog } from '@/composables/useLiveLog'

const mode = ref<'live' | 'history'>('live')
const historicLogs = ref<QueryLogEntry[]>([])
const domainFilter = ref('')
const actionFilter = ref('')

const { entries: liveEntries, status: wsStatus } = useLiveLog(500)

const displayedLogs = computed(() =>
  mode.value === 'live' ? liveEntries.value : historicLogs.value
)

async function fetchLogs() {
  const { data } = await statsApi.logs({
    limit: 200,
    domain: domainFilter.value || undefined,
    action: actionFilter.value || undefined,
  })
  historicLogs.value = data
  mode.value = 'history'
}

onMounted(fetchLogs)
</script>
