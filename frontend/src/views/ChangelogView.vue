<template>
  <div class="p-6 space-y-6">
    <h2 class="text-xl font-bold text-gray-800">Changelog</h2>

    <div v-if="loading" class="text-gray-500">Carregando…</div>

    <div
      v-for="release in releases"
      :key="release.version"
      class="bg-white rounded-xl shadow p-5 space-y-3"
    >
      <div class="flex items-center gap-3">
        <span class="text-lg font-bold text-gray-800">{{ release.version }}</span>
        <span class="text-sm text-gray-400">{{ release.date }}</span>
        <span
          v-if="release.tag === 'latest'"
          class="px-2 py-0.5 text-xs bg-blue-100 text-blue-700 rounded-full font-medium"
        >
          atual
        </span>
      </div>

      <div v-for="section in release.sections" :key="section.title" class="space-y-1">
        <h4 class="text-sm font-semibold text-gray-600">{{ section.title }}</h4>
        <ul class="space-y-0.5 pl-3">
          <li
            v-for="(item, i) in section.items"
            :key="i"
            class="text-sm text-gray-600 flex items-start gap-1.5"
          >
            <span class="mt-0.5 shrink-0 text-gray-400">–</span>
            <span>{{ item }}</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'

interface Section {
  title: string
  items: string[]
}

interface Release {
  version: string
  date: string
  tag?: string
  sections: Section[]
}

const loading = ref(true)
const releases = ref<Release[]>([])

// Lê o CHANGELOG.md do servidor como texto e faz parse simples
async function fetchChangelog() {
  try {
    const res = await fetch('/CHANGELOG.md')
    if (!res.ok) throw new Error('not found')
    const text = await res.text()
    releases.value = parseChangelog(text)
  } catch {
    // fallback — exibe inline
    releases.value = [
      {
        version: 'v2.1.0',
        date: '2026-04-29',
        tag: 'latest',
        sections: [
          {
            title: 'Correções',
            items: [
              'export apiClient corrigido em client.ts',
              'Alert.type removido; timestamp Unix fixado',
              'LogsView usa tipos consistentes com QueryLogEntry',
            ],
          },
          {
            title: 'Novidades',
            items: [
              'BalanceView — gerenciamento de upstreams DNS',
              'ToastContainer — notificações globais 429/5xx',
              'Dashboard com auto-refresh a cada 30s',
              'Badge de alertas não-lidos na sidebar',
            ],
          },
        ],
      },
      {
        version: 'v2.0.0',
        date: '2026-04-01',
        sections: [
          {
            title: 'Backend',
            items: [
              'Migração PHP → Python/FastAPI + DuckDB',
              'Workers assíncronos: LogWatcher, StatsAggregator, AlertChecker',
              'Rate limiting, middlewares CORS e Request-ID',
              'Prometheus metrics em /metrics',
              'GitHub Actions CI/CD com cobertura',
            ],
          },
          {
            title: 'Frontend',
            items: [
              'Vue 3 + TypeScript + Vite + Tailwind CSS',
              'Pinia stores: auth, alerts, ui',
              'WebSocket live-log via VueUse',
              'Chart.js para histórico de consultas',
              'RBAC: rotas admin protegidas',
            ],
          },
        ],
      },
    ]
  } finally {
    loading.value = false
  }
}

function parseChangelog(text: string): Release[] {
  const releases: Release[] = []
  const lines = text.split('\n')

  let current: Release | null = null
  let currentSection: Section | null = null

  for (const line of lines) {
    // ## [x.y.z] — descrição
    const releaseMatch = line.match(/^## \[(.+?)\][^\d]*(\d{4}-\d{2}-\d{2})?/)
    if (releaseMatch) {
      if (current) releases.push(current)
      current = {
        version: releaseMatch[1] ?? line.replace('## ', ''),
        date: releaseMatch[2] ?? '',
        tag: releases.length === 0 ? 'latest' : undefined,
        sections: [],
      }
      currentSection = null
      continue
    }

    // ### Título de seção
    const sectionMatch = line.match(/^### (.+)/)
    if (sectionMatch && current) {
      currentSection = { title: sectionMatch[1], items: [] }
      current.sections.push(currentSection)
      continue
    }

    // - item
    const itemMatch = line.match(/^- (.+)/)
    if (itemMatch && currentSection) {
      // Remove markdown bold/code
      currentSection.items.push(itemMatch[1].replace(/\*\*|`/g, ''))
    }
  }

  if (current) releases.push(current)
  return releases
}

onMounted(fetchChangelog)
</script>
