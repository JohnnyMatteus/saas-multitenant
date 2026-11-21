<script setup lang="ts">
import { mdiAlertCircleOutline, mdiClose } from '@mdi/js'
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { computed, ref, watch } from 'vue'
import { ApiError } from '@/api/client'
import { useCreateSeries, useInstructors, useSessions, useStudio } from '@/api/queries'
import MatRow from '@/components/signature/MatRow.vue'
import { useStudioClock } from '@/composables/useStudioClock'
import { addLocalDays, formatInStudio, hhmm, previewOccurrences, timezoneLabel, windowLabel } from '@/lib/time'
import { seriesFormSchema, WEEKDAYS } from './seriesForm'

const open = defineModel<boolean>({ required: true })
const { timezone, today } = useStudioClock()
const instructors = useInstructors(open)
const studio = useStudio()
const create = useCreateSeries()

const step = ref(1)
const steps = [
  { n: 1, label: 'o quê' },
  { n: 2, label: 'quando' },
  { n: 3, label: 'regras' },
]

const { defineField, handleSubmit, errors, values, validateField, resetForm } = useForm({
  validationSchema: toTypedSchema(seriesFormSchema),
  initialValues: {
    title: '',
    instructor_id: '',
    capacity: 12,
    weekdays: [] as number[],
    start_time: '07:00',
    duration_minutes: 60,
    starts_on: addLocalDays(today.value, 1),
    ends_on: addLocalDays(today.value, 56),
  },
})
const [title, titleAttrs] = defineField('title')
const [instructorId] = defineField('instructor_id')
const [capacity, capacityAttrs] = defineField('capacity')
const [weekdays] = defineField('weekdays')
const [startTime, startTimeAttrs] = defineField('start_time')
const [duration] = defineField('duration_minutes')
const [startsOn, startsOnAttrs] = defineField('starts_on')
const [endsOn, endsOnAttrs] = defineField('ends_on')

watch(open, (isOpen) => {
  if (isOpen) {
    step.value = 1
    create.reset()
    resetForm()
  }
})

function toggleDay(day: number) {
  const current = weekdays.value ?? []
  weekdays.value = current.includes(day) ? current.filter((d) => d !== day) : [...current, day].sort()
}

const stepFields: Record<number, ('title' | 'instructor_id' | 'capacity' | 'weekdays' | 'start_time' | 'duration_minutes' | 'starts_on' | 'ends_on')[]> = {
  1: ['title', 'instructor_id', 'capacity'],
  2: ['weekdays', 'start_time', 'duration_minutes', 'starts_on', 'ends_on'],
}
async function next() {
  const results = await Promise.all((stepFields[step.value] ?? []).map((f) => validateField(f)))
  if (results.every((r) => r.valid)) step.value++
}

const occurrences = computed(() =>
  previewOccurrences({
    weekdays: values.weekdays ?? [],
    startsOn: values.starts_on ?? '',
    endsOn: values.ends_on ?? '',
    startTime: values.start_time ?? '07:00',
    durationMinutes: Number(values.duration_minutes ?? 60),
    timezone: timezone.value,
  }),
)

// Conflito de instrutor: ocorrência da prévia que se sobrepõe a outra aula dele.
const previewRange = computed(() => {
  const list = occurrences.value
  return open.value && list.length ? { from: list[0]!.localDate, to: list[list.length - 1]!.localDate } : null
})
const existing = useSessions(previewRange)
const preview = computed(() =>
  occurrences.value.map((o) => {
    const clash = (existing.data.value ?? []).find(
      (s) => s.instructor.id === values.instructor_id && new Date(s.starts_at) < o.endsAt && new Date(s.ends_at) > o.startsAt,
    )
    return { ...o, clash }
  }),
)
const instructorName = computed(() => instructors.data.value?.find((i) => i.id === values.instructor_id)?.name ?? 'o instrutor')

const durations = [45, 50, 55, 60, 75, 90, 120].map((m) => ({ title: `${m} min`, value: m }))
const created = ref<{ title: string; count: number } | null>(null)

const submit = handleSubmit(async (form) => {
  const series = await create.mutateAsync(form)
  created.value = { title: series.title, count: series.sessions_count ?? 0 }
  open.value = false
})
const submitError = computed(() => (create.error.value instanceof ApiError ? create.error.value.message : create.error.value ? 'Não foi possível criar a aula.' : null))
</script>

<template>
  <v-navigation-drawer v-model="open" temporary location="end" :width="520" color="surface" class="sheet" aria-label="Nova aula recorrente">
    <form class="sheet__inner" novalidate @submit.prevent="step < 3 ? next() : submit()">
      <header class="d-flex align-start mb-4">
        <div>
          <p class="label text-medium mb-1">Nova aula recorrente</p>
          <h2 class="display-2">{{ values.title || 'Sem nome ainda' }}</h2>
        </div>
        <v-spacer />
        <v-btn variant="text" :icon="mdiClose" aria-label="Fechar" height="44" width="44" @click="open = false" />
      </header>

      <ol class="steps mb-6" aria-label="Passos">
        <li v-for="s in steps" :key="s.n" class="steps__item" :class="{ 'steps__item--on': step === s.n, 'steps__item--done': step > s.n }" :aria-current="step === s.n ? 'step' : undefined">
          <button type="button" class="steps__btn" :disabled="s.n > step" @click="step = s.n">
            <span class="steps__n tnum">{{ s.n }}</span> {{ s.label }}
          </button>
        </li>
      </ol>

      <section v-show="step === 1" class="d-flex flex-column ga-2">
        <v-text-field v-model="title" v-bind="titleAttrs" label="Nome da aula" placeholder="Ex.: Vinyasa ao entardecer" :error-messages="errors.title" autofocus />
        <v-select
          v-model="instructorId"
          label="Quem dá a aula"
          :items="instructors.data.value ?? []"
          item-title="name"
          item-value="id"
          :loading="instructors.isPending.value"
          :error-messages="errors.instructor_id"
          no-data-text="Nenhum instrutor cadastrado no estúdio ainda"
        />
        <v-text-field v-model.number="capacity" v-bind="capacityAttrs" type="number" min="1" max="200" label="Capacidade em tapetes" :error-messages="errors.capacity" />
        <div class="capacity-preview" aria-live="polite">
          <MatRow :capacity="Math.min(Math.max(Number(capacity) || 0, 0), 60)" :confirmed="0" size="app" />
          <p class="label text-medium mt-2">Assim a turma aparece para o aluno: {{ Number(capacity) || 0 }} tapetes livres.</p>
        </div>
      </section>

      <section v-show="step === 2" class="d-flex flex-column ga-2">
        <fieldset class="days">
          <legend class="label mb-2">Dias da semana</legend>
          <div class="d-flex flex-wrap ga-2">
            <button
              v-for="d in WEEKDAYS"
              :key="d.value"
              type="button"
              class="day"
              :class="{ 'day--on': weekdays?.includes(d.value) }"
              :aria-pressed="weekdays?.includes(d.value) ?? false"
              :aria-label="d.long"
              @click="toggleDay(d.value)"
            >
              {{ d.short }}
            </button>
          </div>
          <p v-if="errors.weekdays" class="field-error mt-1">{{ errors.weekdays }}</p>
        </fieldset>
        <div class="d-flex ga-3 mt-2">
          <v-text-field v-model="startTime" v-bind="startTimeAttrs" type="time" label="Começa às" :error-messages="errors.start_time" />
          <v-select v-model="duration" :items="durations" label="Duração" />
        </div>
        <div class="d-flex ga-3">
          <v-text-field v-model="startsOn" v-bind="startsOnAttrs" type="date" label="A partir de" :error-messages="errors.starts_on" />
          <v-text-field v-model="endsOn" v-bind="endsOnAttrs" type="date" label="Até" :error-messages="errors.ends_on" />
        </div>
        <v-alert color="info" density="compact" class="mb-2">Horários no {{ timezoneLabel(timezone) }}, o fuso do estúdio, para todos os alunos.</v-alert>
      </section>

      <section v-show="step === 3" class="d-flex flex-column ga-3">
        <div class="rule">
          <h3 class="section-title mb-1">Janela de cancelamento</h3>
          <p class="text-medium mb-1">
            O aluno cancela sem custo até <strong>{{ windowLabel(studio.data.value?.cancellation_window_minutes ?? 0) }}</strong>. Depois disso, só a recepção cancela.
          </p>
          <p class="label text-medium">A regra vale para todas as aulas do estúdio e muda em Ajustes.</p>
        </div>
        <div class="rule">
          <h3 class="section-title mb-1">Lista de espera</h3>
          <p class="text-medium">Com a turma lotada, o aluno entra na fila. Quando alguém cancela, o primeiro da fila é confirmado na hora e recebe um e-mail.</p>
        </div>
      </section>

      <section class="preview mt-6" aria-labelledby="preview-title">
        <h3 id="preview-title" class="label text-medium mb-2">Próximas ocorrências</h3>
        <ul v-if="preview.length" class="preview__list">
          <li v-for="o in preview" :key="o.localDate" class="preview__item" :class="{ 'preview__item--clash': o.clash }">
            <span class="text-capitalize">{{ formatInStudio(o.startsAt, timezone, "EEE, d 'de' MMM") }}</span>
            <span class="time ml-auto">{{ hhmm(o.startsAt, timezone) }}–{{ hhmm(o.endsAt, timezone) }}</span>
            <span v-if="o.clash" class="preview__clash label">
              <v-icon :icon="mdiAlertCircleOutline" size="16" /> conflito: {{ instructorName }} já dá {{ o.clash.title }} às {{ hhmm(o.clash.starts_at, timezone) }}
            </span>
          </li>
        </ul>
        <p v-else class="label text-medium">Escolha os dias e o período para ver as datas geradas.</p>
      </section>

      <v-alert v-if="submitError" color="error" class="mt-4">{{ submitError }}</v-alert>

      <footer class="sheet__footer">
        <v-btn v-if="step > 1" variant="text" @click="step--">Voltar</v-btn>
        <v-spacer />
        <v-btn v-if="step < 3" color="primary" type="submit">Continuar</v-btn>
        <v-btn v-else color="primary" type="submit" :loading="create.isPending.value">Criar aula recorrente</v-btn>
      </footer>
    </form>
  </v-navigation-drawer>

  <v-snackbar :model-value="created !== null" color="success" :timeout="5000" @update:model-value="created = null">
    {{ created?.title }} criada: {{ created?.count }} aulas na grade.
  </v-snackbar>
</template>

<style scoped>
.sheet {
  box-shadow: -12px 0 40px rgba(0, 0, 0, 0.12) !important;
  border-radius: 24px 0 0 24px;
}
.sheet__inner {
  padding: 24px;
  display: flex;
  flex-direction: column;
  min-height: 100%;
}
.steps {
  display: flex;
  gap: 8px;
  list-style: none;
  padding: 0;
}
.steps__btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px 6px 6px;
  border-radius: 999px;
  border: 0;
  background: rgb(var(--v-theme-surface-variant));
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font: inherit;
  font-size: 14px;
  cursor: pointer;
}
.steps__btn:disabled {
  cursor: default;
}
.steps__n {
  width: 24px;
  height: 24px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 600;
  background: rgb(var(--v-theme-surface));
}
.steps__item--on .steps__btn {
  background: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
}
.steps__item--on .steps__n {
  color: rgb(var(--v-theme-primary));
}
.steps__item--done .steps__btn {
  color: rgb(var(--v-theme-on-surface));
}
.capacity-preview {
  padding: 16px;
  border-radius: 20px;
  background: rgb(var(--v-theme-background));
}
.days {
  border: 0;
}
.day {
  min-width: 52px;
  height: 44px;
  padding: 0 12px;
  border-radius: 999px;
  border: 1.5px solid rgb(var(--v-theme-mat-free));
  background: transparent;
  color: rgb(var(--v-theme-on-surface));
  font: inherit;
  font-weight: 500;
  cursor: pointer;
}
.day--on {
  background: rgb(var(--v-theme-primary));
  border-color: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
}
.field-error {
  color: rgb(var(--v-theme-error));
  font-size: 12px;
}
.rule {
  padding: 16px;
  border-radius: 20px;
  background: rgb(var(--v-theme-background));
}
.preview__list {
  list-style: none;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.preview__item {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  padding: 10px 14px;
  border-radius: 12px;
  background: rgb(var(--v-theme-background));
}
.preview__item--clash {
  box-shadow: inset 3px 0 0 rgb(var(--v-theme-error));
}
.preview__clash {
  flex-basis: 100%;
  color: rgb(var(--v-theme-error));
  margin-top: 2px;
}
.sheet__footer {
  display: flex;
  gap: 8px;
  margin-top: auto;
  padding-top: 24px;
}
</style>
