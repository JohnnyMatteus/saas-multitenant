<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ApiError } from '@/api/client'
import { useEditSession, useInstructors } from '@/api/queries'
import { skipReasonLabels, type ClassSession, type EditScope, type SessionEditResult } from '@/api/schemas'
import { hhmm } from '@/lib/time'

const props = defineProps<{ modelValue: boolean; session: ClassSession }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; done: [message: string] }>()

const instructors = useInstructors(() => props.modelValue)
const edit = useEditSession()

const minutes = computed(() => Math.round((new Date(props.session.ends_at).getTime() - new Date(props.session.starts_at).getTime()) / 60_000))
const scope = ref<EditScope>('occurrence')
const title = ref('')
const instructorId = ref('')
const capacity = ref(1)
const startTime = ref('')
const duration = ref(60)
const error = ref<string | null>(null)

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return
    scope.value = 'occurrence'
    title.value = props.session.title
    instructorId.value = props.session.instructor.id
    capacity.value = props.session.capacity
    startTime.value = hhmm(props.session.starts_at, props.session.timezone)
    duration.value = minutes.value
    error.value = null
  },
  { immediate: true },
)

const inSeries = computed(() => props.session.series_id !== null)
const scopes = computed(() => {
  const base = [{ title: 'Só esta aula', value: 'occurrence' as const }]
  return inSeries.value
    ? [...base, { title: 'Esta e as próximas da série', value: 'following' as const }, { title: 'Todas as aulas da série', value: 'all' as const }]
    : base
})

function summary(result: SessionEditResult): string {
  const updated = `${result.updated.length} ${result.updated.length === 1 ? 'aula atualizada' : 'aulas atualizadas'}`
  if (!result.skipped.length) return `${updated}.`
  const why = [...new Set(result.skipped.flatMap((s) => s.reasons.map((r) => skipReasonLabels[r] ?? r)))].join('; ')
  return `${updated}. ${result.skipped.length} ficou de fora: ${why}.`
}

async function submit() {
  const s = props.session
  const input = {
    scope: scope.value,
    ...(title.value !== s.title && { title: title.value }),
    ...(instructorId.value !== s.instructor.id && { instructor_id: instructorId.value }),
    ...(capacity.value !== s.capacity && { capacity: Number(capacity.value) }),
    ...(startTime.value !== hhmm(s.starts_at, s.timezone) && { start_time: startTime.value }),
    ...(Number(duration.value) !== minutes.value && { duration_minutes: Number(duration.value) }),
  }
  if (Object.keys(input).length === 1) {
    error.value = 'Altere ao menos um campo.'
    return
  }
  try {
    const result = await edit.mutateAsync({ id: s.id, input })
    emit('done', summary(result))
    emit('update:modelValue', false)
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Não foi possível salvar.'
  }
}
</script>

<template>
  <v-dialog :model-value="modelValue" max-width="520" @update:model-value="emit('update:modelValue', $event)">
    <v-card class="pa-6" color="surface">
      <h2 class="display-2 mb-4">Editar aula</h2>
      <v-radio-group v-model="scope" label="Aplicar a" hide-details class="mb-4">
        <v-radio v-for="o in scopes" :key="o.value" :label="o.title" :value="o.value" />
      </v-radio-group>
      <v-text-field v-model="title" label="Nome da aula" />
      <v-select v-model="instructorId" label="Instrutor" :items="instructors.data.value ?? []" item-title="name" item-value="id" />
      <div class="d-flex ga-3">
        <v-text-field v-model="startTime" type="time" label="Começa às" />
        <v-text-field v-model.number="duration" type="number" min="15" label="Duração (min)" />
        <v-text-field v-model.number="capacity" type="number" min="1" label="Tapetes" />
      </div>
      <p v-if="scope !== 'occurrence'" class="label text-medium">Aulas com reservas não mudam de horário; elas aparecem como "ficou de fora" no resultado.</p>
      <v-alert v-if="error" type="error" density="compact" class="mt-2">{{ error }}</v-alert>
      <div class="d-flex justify-end ga-2 mt-4">
        <v-btn variant="text" @click="emit('update:modelValue', false)">Fechar</v-btn>
        <v-btn color="primary" :loading="edit.isPending.value" @click="submit">Salvar</v-btn>
      </div>
    </v-card>
  </v-dialog>
</template>
