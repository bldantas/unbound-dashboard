<template>
  <div class="bg-white rounded-xl shadow p-4">
    <p class="text-sm text-gray-500">{{ label }}</p>
    <p :class="['text-3xl font-bold mt-1', colorClass]">{{ value }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  label: string
  value: string | number
  color?: 'blue' | 'red' | 'green' | 'yellow'
}>()

const colorClass = computed(() => ({
  blue: 'text-blue-600',
  red: 'text-red-600',
  green: 'text-green-600',
  yellow: 'text-yellow-600',
}[props.color ?? 'blue']))
</script>
