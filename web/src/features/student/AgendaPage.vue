<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ApiError } from '@/api/client'
import { useBook, useCancelBooking, useMyBookings, useSessions } from '@/api/queries'
import StateBlock from '@/components/StateBlock.vue'
import { useStudioClock } from '@/composables/useStudioClock'
import { addLocalDays, formatLocalDate, localDateOf, relativeDayLabel } from '@/lib/time'
import ClassCard from './ClassCard.vue'

const { me, now, timezone, today } = useStudioClock()
const range = computed(() => (me.data.value ? { from: today.value, to: addLocalDays(today.value, 13) } : null))
const sessions = useSessions(range, 20_000)
const mine = useMyBookings()
const book = useBook()
const cancel = useCancelBooking()

const days = computed(() => Array.from({ length: 14 }, (_, i) => addLocalDays(today.value, i)))
const selected = ref<string>('')
watch(today, (t) => { if (!selected.value || selected.value < t) selected.value = t }, { immediate: true })

const bySession = computed(() => new Map((mine.data.value ?? []).filter((b) => b.status !== 'canceled').map((b) => [b.session_id, b])))
const countByDay = computed(() => {
  const map = new Map<string, number>()
  for (const s of sessions.data.value ?? []) {
    const d = localDateOf(s.starts_at, timezone.value)
    map.set(d, (map.get(d) ?? 0) + 1)
  }
  return map
})
const ofDay = computed(() => (sessions.data.value ?? []).filter((s) => localDateOf(s.starts_at, timezone.value) === selected.value))

const feedback = ref<string | null>(null)
const busyId = ref<string | null>(null)

async function run(sessionId: string, action: () => Promise<string>) {
  busyId.value = sessionId
  try {
    feedback.value = await action()
  } catch (error) {
    feedback.value = error instanceof ApiError ? error.message : 'Não foi possível concluir. Tente de novo.'
  } finally {
    busyId.value = null
  }
}

const onBook = (sessionId: string) =>
  run(sessionId, async () => {
    const b = await book.mutateAsync(sessionId)
    return b.status === 'waitlisted' ? `Você entrou na fila: é o ${b.waitlist_position}º.` : 'Reserva confirmada. Te esperamos.'
  })

const onCancel = (sessionId: string, bookingId: string) =>
  run(sessionId, async () => {
    await cancel.mutateAsync(bookingId)
    return 'Reserva cancelada.'
  })
</script>

<template>
  <h1 class="display-1 mb-1">Agenda</h1>
  <p class="text-medium mb-4">Próximos 14 dias no horário do estúdio.</p>

  <div class="strip" role="tablist" aria-label="Dias">
    <button
      v-for="d in days"
      :key="d"
      role="tab"
      :aria-selected="d === selected"
      class="strip__day"
      :class="{ 'strip__day--on': d === selected }"
      @click="selected = d"
    >
      <span class="label text-capitalize">{{ formatLocalDate(d, 'EEE') }}</span>
      <span class="strip__num tnum">{{ formatLocalDate(d, 'd') }}</span>
      <span class="strip__dot" :class="{ 'strip__dot--has': countByDay.get(d) }" aria-hidden="true" />
    </button>
  </div>

  <h2 class="section-title text-capitalize-first mt-5 mb-3">{{ relativeDayLabel(selected, today) }}</h2>

  <StateBlock v-if="sessions.isPending.value" kind="loading" title="Buscando as aulas…" />
  <StateBlock v-else-if="sessions.isError.value" kind="error" title="Não consegui carregar a agenda" :text="sessions.error.value?.message" @retry="sessions.refetch()" />
  <StateBlock v-else-if="!ofDay.length" title="Sem aula neste dia" text="Olhe os outros dias da faixa acima; os pontos marcam onde há aula." compact />
  <div v-else class="d-flex flex-column ga-3">
    <ClassCard
      v-for="s in ofDay"
      :key="s.id"
      :session="s"
      :booking="bySession.get(s.id)"
      :now="now"
      :busy="busyId === s.id"
      @book="onBook(s.id)"
      @cancel="bySession.get(s.id) && onCancel(s.id, bySession.get(s.id)!.id)"
    />
  </div>

  <v-snackbar :model-value="!!feedback" timeout="4500" location="top" @update:model-value="feedback = null">{{ feedback }}</v-snackbar>
</template>

<style scoped>
.strip {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  margin: 0 -20px;
  padding: 4px 20px 8px;
  scrollbar-width: none;
}
.strip__day {
  flex: 0 0 56px;
  min-height: 72px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  border-radius: 16px;
  color: inherit;
  background: rgb(var(--v-theme-surface));
}
.strip__day:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}
.strip__day--on {
  background: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
}
.strip__num {
  font-size: 20px;
  font-weight: 600;
}
.strip__dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: transparent;
}
.strip__dot--has {
  background: currentColor;
  opacity: 0.6;
}
</style>
