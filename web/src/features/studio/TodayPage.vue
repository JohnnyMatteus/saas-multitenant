<script setup lang="ts">
import { mdiPlus } from '@mdi/js'
import { computed, nextTick, ref } from 'vue'
import { useSessions } from '@/api/queries'
import type { ClassSession } from '@/api/schemas'
import StateBlock from '@/components/StateBlock.vue'
import DayRuler from '@/components/signature/DayRuler.vue'
import StudioLogo from '@/components/StudioLogo.vue'
import { useStudioClock } from '@/composables/useStudioClock'
import { addLocalDays, formatLocalDate, hhmm, localDateOf, relativeDayLabel, timezoneLabel } from '@/lib/time'
import { useUiStore } from '@/stores/ui'
import SessionRow from './SessionRow.vue'

const ui = useUiStore()
const { me, now, timezone, today } = useStudioClock()
const range = computed(() => (me.data.value ? { from: today.value, to: addLocalDays(today.value, 6) } : null))
const sessions = useSessions(range, 20_000)

const isAdmin = computed(() => me.data.value?.role === 'studio_admin')
const all = computed(() => sessions.data.value ?? [])
const todays = computed(() => all.value.filter((s) => localDateOf(s.starts_at, timezone.value) === today.value))
const canOpen = (s: ClassSession) => isAdmin.value || s.instructor.id === me.data.value?.id

// "Precisa de você": no máximo três pendências do domínio, as mais urgentes primeiro.
const attention = computed(() => {
  if (!isAdmin.value) return []
  const upcoming = all.value.filter((s) => new Date(s.starts_at) > now.value)
  const queues = upcoming
    .filter((s) => s.waitlist_count > 0)
    .sort((a, b) => b.waitlist_count - a.waitlist_count)
    .map((s) => ({
      session: s,
      tone: 'secondary',
      title: `${s.waitlist_count} ${s.waitlist_count === 1 ? 'pessoa esperando' : 'pessoas esperando'} vaga`,
      text: `${s.title}, ${relativeDayLabel(localDateOf(s.starts_at, timezone.value), today.value)} às ${hhmm(s.starts_at, timezone.value)}. Quem estiver na fila é promovido sozinho se alguém cancelar.`,
    }))
  const soon = upcoming
    .filter((s) => new Date(s.starts_at).getTime() - now.value.getTime() < 3 * 3600_000 && s.available_spots > 0 && s.confirmed_count < s.capacity / 2)
    .map((s) => ({
      session: s,
      tone: 'warning',
      title: `Começa às ${hhmm(s.starts_at, timezone.value)} com ${s.available_spots} tapetes livres`,
      text: `${s.title} com ${s.instructor.name}. Vale avisar os alunos da turma.`,
    }))
  return [...queues, ...soon].slice(0, 3)
})

const selected = ref<string | null>(null)
async function focusSession(id: string) {
  selected.value = id
  await nextTick()
  document.querySelector(`[data-session="${id}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}
</script>

<template>
  <header class="today-head">
    <div class="d-flex align-center ga-4 flex-wrap">
      <StudioLogo :name="me.data.value?.studio.name ?? 'Estúdio'" :size="48" class="d-md-none" />
      <div>
        <p class="label text-medium mb-1">{{ me.data.value?.studio.name }}</p>
        <h1 class="display-1 text-capitalize-first">{{ formatLocalDate(today, "EEEE, d 'de' MMMM") }}</h1>
      </div>
      <v-spacer />
      <v-chip color="info" variant="tonal" size="default" class="tz-chip">{{ timezoneLabel(timezone) }}</v-chip>
      <v-btn v-if="isAdmin" color="primary" :prepend-icon="mdiPlus" @click="ui.createSeriesOpen = true">Nova aula recorrente</v-btn>
    </div>
    <DayRuler class="mt-4" :timezone="timezone" :sessions="todays" :now="now" :selected-id="selected" @select="focusSession" />
  </header>

  <StateBlock v-if="sessions.isPending.value" kind="loading" title="Lendo a grade de hoje…" />
  <StateBlock v-else-if="sessions.isError.value" kind="error" title="Não conseguimos ler a grade" :text="sessions.error.value?.message" @retry="sessions.refetch()" />

  <div v-else class="today-grid">
    <section aria-labelledby="today-classes">
      <h2 id="today-classes" class="section-title mb-3">Aulas de hoje</h2>
      <div v-if="todays.length" class="d-flex flex-column ga-2">
        <SessionRow
          v-for="s in todays"
          :key="s.id"
          :session="s"
          :now="now"
          :can-open="canOpen(s)"
          :mine="s.instructor.id === me.data.value?.id"
          :highlighted="s.id === selected"
        />
      </div>
      <StateBlock
        v-else-if="all.length === 0 && isAdmin"
        title="Sua grade ainda está em branco"
        text="Crie uma aula recorrente: ela aparece nesta régua todos os dias em que acontecer, no fuso do estúdio."
        class="empty-card"
      >
        <v-btn color="primary" :prepend-icon="mdiPlus" @click="ui.createSeriesOpen = true">Criar primeira aula recorrente</v-btn>
      </StateBlock>
      <StateBlock v-else title="Nenhuma aula hoje" text="O estúdio respira. As próximas aulas estão na grade da semana." class="empty-card">
        <v-btn variant="tonal" color="primary" :to="{ name: 'week' }">Ver a semana</v-btn>
      </StateBlock>
    </section>

    <aside v-if="isAdmin" aria-labelledby="needs-you" class="needs">
      <h2 id="needs-you" class="section-title mb-3">Precisa de você</h2>
      <div v-if="attention.length" class="d-flex flex-column ga-2">
        <article v-for="item in attention" :key="item.session.id + item.tone" class="need" :class="`need--${item.tone}`">
          <h3 class="need__title">{{ item.title }}</h3>
          <p class="label text-medium mb-2">{{ item.text }}</p>
          <v-btn variant="text" color="primary" size="small" height="32" class="px-0" :to="{ name: 'roster', params: { id: item.session.id } }">ver lista</v-btn>
        </article>
      </div>
      <p v-else class="text-medium need-empty">Nada pedindo sua atenção nos próximos 7 dias: sem filas e sem aulas esvaziadas em cima da hora.</p>
    </aside>
  </div>
</template>

<style scoped>
.today-head {
  margin-bottom: 28px;
}
.text-capitalize-first::first-letter {
  text-transform: uppercase;
}
.tz-chip {
  font-weight: 500;
}
.today-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 300px;
  gap: 32px;
  align-items: start;
}
.empty-card {
  background: rgb(var(--v-theme-surface));
  border-radius: 20px;
}
.need {
  padding: 14px 16px 8px;
  border-radius: 20px;
  background: rgb(var(--v-theme-surface));
  border-left: 4px solid rgb(var(--v-theme-secondary));
}
.need--warning {
  border-left-color: rgb(var(--v-theme-warning));
}
.need__title {
  font-size: 15px;
  line-height: 22px;
  font-weight: 600;
  margin-bottom: 4px;
}
.need-empty {
  font-size: 15px;
}
@media (max-width: 1100px) {
  .today-grid {
    grid-template-columns: 1fr;
  }
}
</style>
