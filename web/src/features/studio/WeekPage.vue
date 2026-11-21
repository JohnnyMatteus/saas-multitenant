<script setup lang="ts">
import { mdiChevronLeft, mdiChevronRight, mdiPlus } from '@mdi/js'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useSessions } from '@/api/queries'
import type { ClassSession } from '@/api/schemas'
import StateBlock from '@/components/StateBlock.vue'
import MatRow from '@/components/signature/MatRow.vue'
import { RULER_END_HOUR, RULER_START_HOUR } from '@/components/signature/ruler'
import { useStudioClock } from '@/composables/useStudioClock'
import { addLocalDays, formatLocalDate, hhmm, localDateOf, minutesOfDay, startOfLocalWeek, timezoneLabel } from '@/lib/time'
import { useUiStore } from '@/stores/ui'
import { assignLanes } from './lanes'

const HOUR_PX = 52
const ui = useUiStore()
const router = useRouter()
const { mdAndUp } = useDisplay()
const { me, now, timezone, today } = useStudioClock()

const offset = ref(0)
const weekStart = computed(() => addLocalDays(startOfLocalWeek(today.value), offset.value * 7))
const days = computed(() => Array.from({ length: 7 }, (_, i) => addLocalDays(weekStart.value, i)))
const range = computed(() => (me.data.value ? { from: days.value[0]!, to: days.value[6]! } : null))
const sessions = useSessions(range, 20_000)
const isAdmin = computed(() => me.data.value?.role === 'studio_admin')

const hours = Array.from({ length: RULER_END_HOUR - RULER_START_HOUR }, (_, i) => RULER_START_HOUR + i)

const byDay = computed(() => {
  const map = new Map<string, ReturnType<typeof assignLanes<ClassSession & { start: number; end: number }>>>()
  for (const day of days.value) {
    const list = (sessions.data.value ?? [])
      .filter((s) => localDateOf(s.starts_at, timezone.value) === day)
      .map((s) => ({ ...s, start: minutesOfDay(s.starts_at, timezone.value), end: minutesOfDay(s.ends_at, timezone.value) || 24 * 60 }))
    map.set(day, assignLanes(list))
  }
  return map
})

function blockStyle(b: { start: number; end: number; lane: number; lanes: number }) {
  const top = ((Math.max(b.start, RULER_START_HOUR * 60) - RULER_START_HOUR * 60) / 60) * HOUR_PX
  const height = Math.max(((Math.min(b.end, RULER_END_HOUR * 60) - Math.max(b.start, RULER_START_HOUR * 60)) / 60) * HOUR_PX, 46)
  return { top: `${top}px`, height: `${height - 4}px`, left: `calc(${(100 / b.lanes) * b.lane}% + 3px)`, width: `calc(${100 / b.lanes}% - 6px)` }
}

const canOpen = (s: ClassSession) => isAdmin.value || s.instructor.id === me.data.value?.id
function open(s: ClassSession) {
  if (canOpen(s)) void router.push({ name: 'roster', params: { id: s.id } })
}
const weekLabel = computed(() => `${formatLocalDate(days.value[0]!, "d 'de' MMM")} a ${formatLocalDate(days.value[6]!, "d 'de' MMM")}`)
const empty = computed(() => sessions.isSuccess.value && (sessions.data.value?.length ?? 0) === 0)
</script>

<template>
  <header class="d-flex align-center flex-wrap ga-3 mb-6">
    <div>
      <p class="label text-medium mb-1">Grade da semana · {{ timezoneLabel(timezone) }}</p>
      <h1 class="display-1">{{ weekLabel }}</h1>
    </div>
    <v-spacer />
    <div class="d-flex align-center ga-1">
      <v-btn variant="text" :icon="mdiChevronLeft" aria-label="Semana anterior" height="44" width="44" @click="offset--" />
      <v-btn variant="tonal" height="36" :disabled="offset === 0" @click="offset = 0">Esta semana</v-btn>
      <v-btn variant="text" :icon="mdiChevronRight" aria-label="Próxima semana" height="44" width="44" @click="offset++" />
    </div>
    <v-btn v-if="isAdmin" color="primary" :prepend-icon="mdiPlus" @click="ui.createSeriesOpen = true">Nova aula recorrente</v-btn>
  </header>

  <StateBlock v-if="sessions.isError.value" kind="error" title="Não conseguimos ler a grade" :text="sessions.error.value?.message" @retry="sessions.refetch()" />

  <div v-else-if="mdAndUp" class="week" :aria-busy="sessions.isPending.value">
    <div class="week__corner" />
    <div v-for="day in days" :key="day" class="week__dayhead" :class="{ 'week__dayhead--today': day === today }">
      <span class="label text-medium text-capitalize">{{ formatLocalDate(day, 'EEE') }}</span>
      <span class="week__daynum tnum">{{ formatLocalDate(day, 'd') }}</span>
    </div>

    <div class="week__hours" :style="{ height: `${hours.length * HOUR_PX}px` }" aria-hidden="true">
      <span v-for="h in hours" :key="h" class="week__hour tnum" :style="{ top: `${(h - RULER_START_HOUR) * HOUR_PX}px` }">{{ h }}h</span>
    </div>
    <div
      v-for="day in days"
      :key="`col-${day}`"
      class="week__col"
      :class="{ 'week__col--today': day === today }"
      :style="{ height: `${hours.length * HOUR_PX}px`, '--hour': `${HOUR_PX}px` }"
      role="list"
      :aria-label="formatLocalDate(day, `EEEE, d 'de' MMMM`)"
    >
      <button
        v-for="b in byDay.get(day)"
        :key="b.id"
        type="button"
        role="listitem"
        class="block"
        :class="{ 'block--past': new Date(b.ends_at) <= now, 'block--locked': !canOpen(b) }"
        :style="blockStyle(b)"
        :aria-label="`${b.title}, ${hhmm(b.starts_at, timezone)} com ${b.instructor.name}`"
        @click="open(b)"
      >
        <span class="time block__time">{{ hhmm(b.starts_at, timezone) }}</span>
        <span class="block__title">{{ b.title }}</span>
        <MatRow :capacity="b.capacity" :confirmed="b.confirmed_count" :waitlist="b.waitlist_count" :max-waitlist="3" class="block__mats" />
      </button>
    </div>
  </div>

  <div v-else class="d-flex flex-column ga-4">
    <section v-for="day in days" :key="`m-${day}`">
      <h2 class="section-title mb-2 text-capitalize">{{ formatLocalDate(day, "EEEE, d 'de' MMM") }}</h2>
      <div v-if="byDay.get(day)?.length" class="d-flex flex-column ga-2">
        <button v-for="b in byDay.get(day)" :key="b.id" type="button" class="mblock" @click="open(b)">
          <span class="time">{{ hhmm(b.starts_at, timezone) }}</span>
          <span class="flex-grow-1 text-left">
            <span class="d-block font-display">{{ b.title }}</span>
            <MatRow :capacity="b.capacity" :confirmed="b.confirmed_count" :waitlist="b.waitlist_count" caption />
          </span>
        </button>
      </div>
      <p v-else class="label text-medium">Sem aulas.</p>
    </section>
  </div>

  <StateBlock
    v-if="empty && isAdmin"
    compact
    class="mt-4"
    title="Semana sem aulas"
    text="Monte uma aula recorrente e ela se repete nos dias escolhidos até a data final."
  />
</template>

<style scoped>
.week {
  display: grid;
  grid-template-columns: 48px repeat(7, minmax(0, 1fr));
  column-gap: 6px;
  background: rgb(var(--v-theme-surface));
  border-radius: 20px;
  padding: 16px 16px 20px 8px;
}
.week__dayhead {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding-bottom: 12px;
}
.week__dayhead--today .week__daynum {
  background: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
}
.week__daynum {
  width: 34px;
  height: 34px;
  border-radius: 999px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 16px;
}
.week__hours {
  position: relative;
}
.week__hour {
  position: absolute;
  right: 8px;
  transform: translateY(-50%);
  font-size: 12px;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}
.week__hour:first-child {
  transform: none;
}
.week__col {
  position: relative;
  border-radius: 12px;
  background-image: repeating-linear-gradient(
    to bottom,
    rgba(var(--v-border-color), var(--v-border-opacity)) 0,
    rgba(var(--v-border-color), var(--v-border-opacity)) 1px,
    transparent 1px,
    transparent var(--hour)
  );
}
.week__col--today {
  background-color: rgba(var(--v-theme-primary), 0.05);
}
.block {
  position: absolute;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 6px 8px;
  border-radius: 12px;
  border: 0;
  text-align: left;
  overflow: hidden;
  cursor: pointer;
  background: rgb(var(--v-theme-surface-variant));
  color: rgb(var(--v-theme-on-surface));
  transition: transform var(--respiro-fast) ease;
}
.block:hover {
  transform: translateY(-1px);
}
.block--past {
  opacity: 0.55;
}
.block--locked {
  cursor: default;
}
.block__time {
  font-size: 12px;
  line-height: 14px;
}
.block__title {
  font-family: var(--respiro-display);
  font-variation-settings: 'SOFT' 100;
  font-size: 14px;
  line-height: 17px;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 100%;
}
.block__mats {
  margin-top: 2px;
}
.mblock {
  display: flex;
  gap: 16px;
  align-items: center;
  padding: 12px 16px;
  min-height: 72px;
  border-radius: 20px;
  border: 0;
  background: rgb(var(--v-theme-surface));
  color: inherit;
}
</style>
