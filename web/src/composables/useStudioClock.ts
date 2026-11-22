import { useIntervalFn, useNow } from '@vueuse/core'
import { computed } from 'vue'
import { useMe } from '@/api/queries'
import { studioToday } from '@/lib/time'

/** Usuário, estúdio e "hoje" sempre no fuso do estúdio, não no do navegador. */
export function useStudioClock() {
  const me = useMe()
  const now = useNow({ scheduler: (cb) => useIntervalFn(cb, 30_000) })
  const timezone = computed(() => me.data.value?.studio.timezone ?? 'America/Sao_Paulo')
  const today = computed(() => studioToday(timezone.value, now.value))
  return { me, now, timezone, today }
}
