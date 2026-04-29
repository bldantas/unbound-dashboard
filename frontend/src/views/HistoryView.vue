<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold text-gray-800">Histórico DNS</h2>
      <select
        v-model="days"
        @change="load"
        class="border border-gray-300 rounded-lg px-3 py-1 text-sm"
      >
        <option :value="7">7 dias</option>
        <option :value="14">14 dias</option>
        <option :value="30">30 dias</option>
        <option :value="90">90 dias</option>
      </select>
    </div>

    <div v-if="loading" class="text-gray-500">Carregando…</div>

    <div v-else class="bg-white rounded-xl shadow p-4">
      <canvas ref="chartRef" height="120"></canvas>
    </div>

    <!-- Tabela resumo -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
          <tr>
            <th class="px-4 py-2 text-left">Data</th>
            <th class="px-4 py-2 text-right">Total</th>
            <th class="px-4 py-2 text-right">Bloqueadas</th>
            <th class="px-4 py-2 text-right">Resolvidas</th>
            <th class="px-4 py-2 text-right">Cache</th>
            <th class="px-4 py-2 text-right">Taxa bloqueio</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in history"
            :key="row.date"
            class="border-t border-gray-100 hover:bg-gray-50"
          >
            <td class="px-4 py-2">{{ row.date }}</td>
            <td class="px-4 py-2 text-right">{{ row.total.toLocaleString() }}</td>
            <td class="px-4 py-2 text-right text-red-600">{{ row.blocked.toLocaleString() }}</td>
            <td class="px-4 py-2 text-right text-green-600">{{ row.resolved.toLocaleString() }}</td>
            <td class="px-4 py-2 text-right text-yellow-600">{{ row.cache_hits.toLocaleString() }}</td>
            <td class="px-4 py-2 text-right font-mono">{{ row.block_rate }}%</td>
          </tr>
          <tr v-if="!history.length">
            <td colspan="6" class="px-4 py-6 text-center text-gray-400">Sem dados históricos</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue'
import {
  Chart,
  LineController,
  LineElement,
  PointElement,
  LinearScale,
  CategoryScale,
  Tooltip,
  Legend,
  Filler,
} from 'chart.js'
import { statsApi, type DailyStat } from '@/api/stats'

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend, Filler)

const days = ref(30)
const history = ref<DailyStat[]>([])
const loading = ref(true)
const chartRef = ref<HTMLCanvasElement | null>(null)
let chartInstance: Chart | null = null

async function load() {
  loading.value = true
  const { data } = await statsApi.history(days.value)
  history.value = [...data].reverse() // mais antigo primeiro para o gráfico
  loading.value = false
  await nextTick()
  renderChart()
}

function renderChart() {
  if (!chartRef.value) return
  if (chartInstance) chartInstance.destroy()

  const labels = history.value.map((r) => r.date)

  chartInstance = new Chart(chartRef.value, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Total',
          data: history.value.map((r) => r.total),
          borderColor: '#3b82f6',
          backgroundColor: 'rgba(59,130,246,0.1)',
          fill: true,
          tension: 0.3,
        },
        {
          label: 'Bloqueadas',
          data: history.value.map((r) => r.blocked),
          borderColor: '#ef4444',
          backgroundColor: 'rgba(239,68,68,0.07)',
          fill: true,
          tension: 0.3,
        },
        {
          label: 'Cache hits',
          data: history.value.map((r) => r.cache_hits),
          borderColor: '#eab308',
          fill: false,
          tension: 0.3,
        },
      ],
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'top' },
      },
      scales: {
        y: { beginAtZero: true },
      },
    },
  })
}

onMounted(load)
</script>
