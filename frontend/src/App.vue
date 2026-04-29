<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { RouterView } from 'vue-router'
import AppLayout from '@/views/AppLayout.vue'
import ToastContainer from '@/components/ToastContainer.vue'
import { useToast } from '@/composables/useToast'

const auth = useAuthStore()
const { show } = useToast()

function onApiError(e: Event) {
  const detail = (e as CustomEvent).detail as { message: string; type: string }
  show(detail.message, detail.type as 'warning' | 'error')
}

onMounted(() => window.addEventListener('api:error', onApiError))
onUnmounted(() => window.removeEventListener('api:error', onApiError))
</script>

<template>
  <AppLayout v-if="auth.isAuthenticated" />
  <RouterView v-else />
  <ToastContainer />
</template>
