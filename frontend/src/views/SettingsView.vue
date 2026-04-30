<template>
  <div class="p-6 space-y-4">
    <h2 class="text-xl font-bold text-gray-800">Configurações</h2>

    <div v-if="loading" class="text-gray-500">Carregando…</div>

    <div v-else class="bg-white rounded-xl shadow divide-y divide-gray-100">
      <div
        v-for="(_, key) in settings"
        :key="key"
        class="flex items-center justify-between px-4 py-3 gap-4"
      >
        <span class="font-mono text-sm text-gray-700">{{ key }}</span>
        <input
          v-model="settings[key]"
          class="border border-gray-300 rounded px-2 py-1 text-sm font-mono w-64"
          @change="save(key)"
        />
      </div>
      <p v-if="!Object.keys(settings).length" class="px-4 py-6 text-gray-400 text-sm">
        Nenhuma configuração encontrada
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api } from '@/api/client'

const settings = ref<Record<string, string>>({})
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await api.get('/settings')
    settings.value = data
  } finally {
    loading.value = false
  }
})

async function save(key: string) {
  await api.put(`/settings/${key}`, { key, value: settings.value[key] })
}
</script>
