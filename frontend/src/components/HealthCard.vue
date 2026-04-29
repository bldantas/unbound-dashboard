<template>
  <div
    class="bg-white rounded-xl shadow p-4 flex flex-col gap-1"
    :class="cardClass"
  >
    <span class="text-2xl">{{ icon }}</span>
    <p class="text-xs text-gray-500 uppercase tracking-wide">{{ label }}</p>
    <p class="text-2xl font-bold" :class="valueClass">
      {{ displayValue }}<span class="text-sm font-normal text-gray-400 ml-1">{{ unit }}</span>
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  label: string
  value: number
  unit: string
  warn: number
  crit: number
  icon?: string
}>()

const level = computed(() => {
  if (props.value >= props.crit) return 'crit'
  if (props.value >= props.warn) return 'warn'
  return 'ok'
})

const cardClass = computed(() => ({
  'border-l-4 border-red-500': level.value === 'crit',
  'border-l-4 border-yellow-400': level.value === 'warn',
  'border-l-4 border-green-500': level.value === 'ok',
}))

const valueClass = computed(() => ({
  'text-red-600': level.value === 'crit',
  'text-yellow-600': level.value === 'warn',
  'text-green-600': level.value === 'ok',
}))

const displayValue = computed(() =>
  typeof props.value === 'number' ? props.value.toFixed(1) : '--'
)
</script>
