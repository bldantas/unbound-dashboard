<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold text-gray-800">Balanceamento de Upstreams</h2>
      <button
        @click="checkHealth"
        :disabled="checking"
        class="text-sm bg-blue-600 hover:bg-blue-700 disabled:opacity-40 text-white px-4 py-2 rounded-lg"
      >
        {{ checking ? 'Verificando…' : '↻ Checar Saúde' }}
      </button>
    </div>

    <!-- Aggregate stats -->
    <div v-if="stats" class="grid grid-cols-3 gap-4">
      <div class="bg-white rounded-xl shadow p-4 text-center">
        <p class="text-xs text-gray-500 mb-1">Total de consultas</p>
        <p class="text-2xl font-bold text-blue-600">{{ stats.total_queries.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-xl shadow p-4 text-center">
        <p class="text-xs text-gray-500 mb-1">Cache hits</p>
        <p class="text-2xl font-bold text-green-600">{{ stats.total_cache_hits.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-xl shadow p-4 text-center">
        <p class="text-xs text-gray-500 mb-1">Taxa de cache</p>
        <p class="text-2xl font-bold text-yellow-600">{{ (stats.cache_hit_ratio * 100).toFixed(1) }}%</p>
      </div>
    </div>

    <!-- Lista de upstreams -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-semibold text-gray-700">Upstreams configurados</h3>
        <button
          @click="showAddForm = !showAddForm"
          class="text-sm text-blue-600 hover:underline"
        >
          + Adicionar
        </button>
      </div>

      <!-- Formulário de adição -->
      <div v-if="showAddForm" class="px-4 py-3 border-b border-gray-100 bg-gray-50 space-y-3">
        <div class="flex gap-2">
          <input
            v-model="form.address"
            placeholder="Endereço (ex: 127.0.0.1)"
            class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm"
          />
          <input
            v-model.number="form.port"
            type="number"
            placeholder="Porta"
            class="w-24 border border-gray-300 rounded-lg px-3 py-2 text-sm"
          />
          <input
            v-model="form.label"
            placeholder="Rótulo"
            class="w-36 border border-gray-300 rounded-lg px-3 py-2 text-sm"
          />
        </div>
        <div class="flex gap-2">
          <button
            @click="addUpstream"
            class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg"
          >
            Adicionar
          </button>
          <button
            @click="showAddForm = false"
            class="text-sm text-gray-500 hover:text-gray-700 px-4 py-2"
          >
            Cancelar
          </button>
        </div>
        <p v-if="formError" class="text-xs text-red-600">{{ formError }}</p>
      </div>

      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
          <tr>
            <th class="px-4 py-2 text-left">Endereço</th>
            <th class="px-4 py-2 text-left">Rótulo</th>
            <th class="px-4 py-2 text-center">Ativo</th>
            <th class="px-4 py-2 text-center">Saúde</th>
            <th class="px-4 py-2 text-center">Latência</th>
            <th class="px-4 py-2 text-center">Ações</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="up in enrichedUpstreams" :key="`${up.address}:${up.port}`">
            <td class="px-4 py-3 font-mono text-gray-800">{{ up.address }}:{{ up.port }}</td>
            <td class="px-4 py-3 text-gray-600">{{ up.label || '—' }}</td>
            <td class="px-4 py-3 text-center">
              <button
                @click="toggleEnabled(up)"
                :class="[
                  'w-10 h-5 rounded-full transition-colors',
                  up.enabled ? 'bg-green-500' : 'bg-gray-300',
                ]"
              >
                <span
                  :class="[
                    'block w-4 h-4 bg-white rounded-full shadow transition-transform mx-0.5',
                    up.enabled ? 'translate-x-5' : '',
                  ]"
                />
              </button>
            </td>
            <td class="px-4 py-3 text-center">
              <span
                v-if="statusMap[`${up.address}:${up.port}`] !== undefined"
                :class="[
                  'px-2 py-0.5 rounded-full text-xs font-medium',
                  statusMap[`${up.address}:${up.port}`].healthy
                    ? 'bg-green-100 text-green-700'
                    : 'bg-red-100 text-red-700',
                ]"
              >
                {{ statusMap[`${up.address}:${up.port}`].healthy ? 'OK' : 'Falha' }}
              </span>
              <span v-else class="text-gray-300 text-xs">—</span>
            </td>
            <td class="px-4 py-3 text-center font-mono text-gray-500 text-xs">
              <template v-if="statusMap[`${up.address}:${up.port}`]?.latency_ms != null">
                {{ statusMap[`${up.address}:${up.port}`].latency_ms?.toFixed(1) }}ms
              </template>
              <span v-else class="text-gray-300">—</span>
            </td>
            <td class="px-4 py-3 text-center">
              <button
                @click="removeUpstream(up.address, up.port)"
                class="text-red-400 hover:text-red-600 text-xs"
              >
                Remover
              </button>
            </td>
          </tr>
          <tr v-if="!upstreams.length">
            <td colspan="6" class="px-4 py-6 text-center text-gray-400 text-sm">
              Nenhum upstream configurado
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { balanceApi, type UpstreamConfig, type UpstreamStatus } from '@/api/balance'
import { useToast } from '@/composables/useToast'
import type { BalanceStats } from '@/api/balance'

const { show } = useToast()

const upstreams = ref<UpstreamConfig[]>([])
const statusList = ref<UpstreamStatus[]>([])
const stats = ref<BalanceStats | null>(null)
const checking = ref(false)
const showAddForm = ref(false)
const formError = ref<string | null>(null)

const form = ref({ address: '', port: 5335, label: '', enabled: true })

const statusMap = computed(() => {
  const map: Record<string, UpstreamStatus> = {}
  for (const s of statusList.value) {
    map[`${s.address}:${s.port}`] = s
  }
  return map
})

const enrichedUpstreams = computed(() =>
  upstreams.value.map((u) => ({ ...u, key: `${u.address}:${u.port}` }))
)

async function load() {
  const [listRes, statsRes] = await Promise.all([
    balanceApi.listUpstreams(),
    balanceApi.getStats(),
  ])
  upstreams.value = listRes.data
  stats.value = statsRes.data
}

async function checkHealth() {
  checking.value = true
  try {
    const { data } = await balanceApi.checkHealth()
    statusList.value = data
    show(`${data.filter((s) => s.healthy).length}/${data.length} upstreams saudáveis`, 'info')
  } catch {
    show('Erro ao verificar saúde dos upstreams.', 'error')
  } finally {
    checking.value = false
  }
}

async function addUpstream() {
  formError.value = null
  if (!form.value.address) {
    formError.value = 'Endereço obrigatório.'
    return
  }
  try {
    await balanceApi.addUpstream({ ...form.value })
    await load()
    showAddForm.value = false
    form.value = { address: '', port: 5335, label: '', enabled: true }
    show('Upstream adicionado.', 'success')
  } catch {
    formError.value = 'Erro ao adicionar upstream.'
  }
}

async function removeUpstream(address: string, port: number) {
  await balanceApi.removeUpstream(address, port)
  await load()
  show('Upstream removido.', 'success')
}

async function toggleEnabled(up: UpstreamConfig) {
  await balanceApi.setEnabled(up.address, up.port, !up.enabled)
  await load()
}

onMounted(async () => {
  await load()
  await checkHealth()
})
</script>
