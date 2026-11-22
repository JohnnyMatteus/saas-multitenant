<script setup lang="ts">
import { useIntervalFn, useNow } from '@vueuse/core'
import { computed } from 'vue'
import { hhmm, minutesOfDay } from '@/lib/time'
import { clampPercent, rulerPercent, RULER_END_HOUR, RULER_START_HOUR } from './ruler'

/**
 * Régua do dia: 6h às 22h, do linho da manhã ao ameixa suave da noite,
 * com as aulas do dia marcadas e o "agora" no fuso do estúdio.
 */
const props = withDefaults(
  defineProps<{
    timezone: string
    sessions?: { id: string; title: string; starts_at: string; ends_at: string; full?: boolean }[]
    showNow?: boolean
    now?: Date
    selectedId?: string | null
  }>(),
  { sessions: () => [], showNow: true, now: undefined, selectedId: null },
)
const emit = defineEmits<{ select: [id: string] }>()

const liveNow = useNow({ scheduler: (cb) => useIntervalFn(cb, 30_000) })
const now = computed(() => props.now ?? liveNow.value)

const hours = computed(() => {
  const list: { h: number; left: number }[] = []
  for (let h = RULER_START_HOUR; h <= RULER_END_HOUR; h += 2) list.push({ h, left: clampPercent(h * 60) })
  return list
})

const nowPercent = computed(() => (props.showNow ? rulerPercent(minutesOfDay(now.value, props.timezone)) : null))
const nowLabel = computed(() => hhmm(now.value, props.timezone))

const blocks = computed(() =>
  props.sessions.map((s) => {
    const left = clampPercent(minutesOfDay(s.starts_at, props.timezone))
    const right = clampPercent(minutesOfDay(s.ends_at, props.timezone))
    return {
      ...s,
      left,
      width: Math.max(right - left, 1.2),
      past: new Date(s.ends_at) <= now.value,
      label: `${s.title}, ${hhmm(s.starts_at, props.timezone)} às ${hhmm(s.ends_at, props.timezone)}`,
    }
  }),
)

const summary = computed(() => {
  const count = props.sessions.length
  const aulas = count === 0 ? 'nenhuma aula' : count === 1 ? '1 aula' : `${count} aulas`
  const agora = nowPercent.value !== null ? `, agora ${nowLabel.value}` : ''
  return `Régua do dia, das ${RULER_START_HOUR}h às ${RULER_END_HOUR}h, ${aulas}${agora}`
})
</script>

<template>
  <section class="ruler" :aria-label="summary">
    <div class="ruler__track">
      <button
        v-for="b in blocks"
        :key="b.id"
        type="button"
        class="ruler__class"
        :class="{ 'ruler__class--past': b.past, 'ruler__class--full': b.full, 'ruler__class--selected': b.id === selectedId }"
        :style="{ left: `${b.left}%`, width: `${b.width}%` }"
        :aria-label="b.label"
        :title="b.label"
        data-testid="ruler-class"
        @click="emit('select', b.id)"
      />
      <div v-if="nowPercent !== null" class="ruler__now" :style="{ left: `${nowPercent}%` }" data-testid="ruler-now">
        <span class="ruler__now-label label tnum">agora {{ nowLabel }}</span>
      </div>
    </div>
    <div class="ruler__hours" aria-hidden="true">
      <span v-for="t in hours" :key="t.h" class="ruler__hour tnum" :style="{ left: `${t.left}%` }">{{ t.h }}h</span>
    </div>
  </section>
</template>

<style scoped>
.ruler {
  position: relative;
  padding: 22px 0 0;
}
.ruler__track {
  position: relative;
  height: 28px;
  border-radius: 999px;
  background: linear-gradient(90deg, rgb(var(--v-theme-day-dawn)) 0%, rgb(var(--v-theme-surface-variant)) 45%, rgb(var(--v-theme-day-dusk)) 100%);
  box-shadow: inset 0 0 0 1px rgba(var(--v-border-color), var(--v-border-opacity));
}
.ruler__class {
  position: absolute;
  top: 6px;
  height: 16px;
  border-radius: 999px;
  border: 0;
  padding: 0;
  cursor: pointer;
  background: rgb(var(--v-theme-primary));
  transition: transform var(--respiro-fast) ease;
}
.ruler__class:hover {
  transform: scaleY(1.2);
}
.ruler__class--past {
  opacity: 0.4;
}
.ruler__class--selected {
  box-shadow: 0 0 0 2px rgb(var(--v-theme-background)), 0 0 0 4px rgb(var(--v-theme-on-surface));
}
.ruler__now {
  position: absolute;
  top: -8px;
  bottom: -6px;
  width: 2px;
  margin-left: -1px;
  background: rgb(var(--v-theme-on-surface));
  border-radius: 2px;
  pointer-events: none;
}
.ruler__now-label {
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translateX(-50%);
  white-space: nowrap;
  padding-bottom: 2px;
  color: rgb(var(--v-theme-on-surface));
}
.ruler__hours {
  position: relative;
  height: 20px;
  margin-top: 6px;
}
.ruler__hour {
  position: absolute;
  transform: translateX(-50%);
  font-size: 12px;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}
.ruler__hour:first-child {
  transform: none;
}
.ruler__hour:last-child {
  transform: translateX(-100%);
}
</style>
