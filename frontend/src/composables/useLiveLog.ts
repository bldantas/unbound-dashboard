import { useWebSocket } from '@vueuse/core'
import { ref, watch } from 'vue'
import type { QueryLogEntry } from '@/api/stats'

// Formato bruto que chega pelo WebSocket (pub/sub do worker)
interface RawLogEntry {
  ts: number
  ip: string
  domain: string
  type: string
  action: string
}

export function useLiveLog(maxLines = 200) {
  const wsUrl = `${location.protocol === 'https:' ? 'wss' : 'ws'}://${location.host}/ws/live-log`

  const entries = ref<QueryLogEntry[]>([])

  const { data, status } = useWebSocket(wsUrl, {
    autoReconnect: {
      retries: 10,
      delay: 2000,
      onFailed() {
        console.warn('WebSocket live-log: falhou após 10 tentativas')
      },
    },
    heartbeat: {
      message: 'ping',
      interval: 30_000,
      pongTimeout: 5_000,
    },
  })

  watch(data, (raw) => {
    if (!raw) return
    try {
      const e: RawLogEntry = JSON.parse(raw)
      // Normaliza para o mesmo formato de QueryLogEntry
      const entry: QueryLogEntry = {
        timestamp: e.ts,
        client_ip: e.ip,
        domain: e.domain,
        query_type: e.type,
        action: e.action,
      }
      entries.value.unshift(entry)
      if (entries.value.length > maxLines) {
        entries.value.length = maxLines
      }
    } catch {
      // ignora mensagens não-JSON (ex: pong)
    }
  })

  return { entries, status }
}
