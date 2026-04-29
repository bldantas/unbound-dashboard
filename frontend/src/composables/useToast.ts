import { ref } from 'vue'

export type ToastType = 'info' | 'success' | 'warning' | 'error'

export interface Toast {
  id: number
  message: string
  type: ToastType
}

let _nextId = 0
const toasts = ref<Toast[]>([])

export function useToast() {
  function show(message: string, type: ToastType = 'info', duration = 4000) {
    const id = ++_nextId
    toasts.value.push({ id, message, type })
    setTimeout(() => dismiss(id), duration)
  }

  function dismiss(id: number) {
    const idx = toasts.value.findIndex((t) => t.id === id)
    if (idx >= 0) toasts.value.splice(idx, 1)
  }

  return { toasts, show, dismiss }
}
