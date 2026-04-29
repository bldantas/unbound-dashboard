<template>
  <Teleport to="body">
    <div class="fixed top-4 right-4 z-50 flex flex-col gap-2 w-80">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        :class="[
          'rounded-lg px-4 py-3 shadow-lg text-sm text-white flex items-start gap-2 transition-all',
          toast.type === 'success' ? 'bg-green-600' :
          toast.type === 'warning' ? 'bg-yellow-500' :
          toast.type === 'error'   ? 'bg-red-600'   : 'bg-blue-600',
        ]"
      >
        <span class="flex-1">{{ toast.message }}</span>
        <button @click="dismiss(toast.id)" class="opacity-70 hover:opacity-100">✕</button>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { useToast } from '@/composables/useToast'

const { toasts, dismiss } = useToast()
</script>
