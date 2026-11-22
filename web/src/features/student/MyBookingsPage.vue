<script setup lang="ts">
import { computed, ref } from 'vue'
import { ApiError } from '@/api/client'
import { useCancelBooking, useMyBookings } from '@/api/queries'
import StateBlock from '@/components/StateBlock.vue'
import { useStudioClock } from '@/composables/useStudioClock'
import ClassCard from './ClassCard.vue'

const { now } = useStudioClock()
const mine = useMyBookings()
const cancel = useCancelBooking()
const feedback = ref<string | null>(null)
const busyId = ref<string | null>(null)

const active = computed(() =>
  (mine.data.value ?? [])
    .filter((b) => b.status !== 'canceled' && b.session)
    .sort((a, b) => a.session!.starts_at.localeCompare(b.session!.starts_at)),
)
const upcoming = computed(() => active.value.filter((b) => new Date(b.session!.ends_at) > now.value))
const past = computed(() => active.value.filter((b) => new Date(b.session!.ends_at) <= now.value).reverse().slice(0, 5))

async function onCancel(bookingId: string) {
  busyId.value = bookingId
  try {
    await cancel.mutateAsync(bookingId)
    feedback.value = 'Reserva cancelada.'
  } catch (error) {
    feedback.value = error instanceof ApiError ? error.message : 'Não foi possível cancelar.'
  } finally {
    busyId.value = null
  }
}
</script>

<template>
  <h1 class="display-1 mb-4">Minhas aulas</h1>

  <StateBlock v-if="mine.isPending.value" kind="loading" title="Buscando suas reservas…" />
  <StateBlock v-else-if="mine.isError.value" kind="error" title="Não consegui carregar suas reservas" :text="mine.error.value?.message" @retry="mine.refetch()" />
  <template v-else>
    <StateBlock v-if="!upcoming.length" title="Nenhuma aula reservada" text="Escolha um horário na agenda; se estiver lotado, você entra na fila e é avisado se abrir vaga.">
      <v-btn color="primary" :to="{ name: 'agenda' }">Ver a agenda</v-btn>
    </StateBlock>
    <div v-else class="d-flex flex-column ga-3">
      <ClassCard
        v-for="b in upcoming"
        :key="b.id"
        :session="b.session!"
        :booking="b"
        :now="now"
        :busy="busyId === b.id"
        show-date
        @cancel="onCancel(b.id)"
      />
    </div>

    <template v-if="past.length">
      <h2 class="section-title mt-8 mb-3">Aulas recentes</h2>
      <div class="d-flex flex-column ga-3">
        <ClassCard v-for="b in past" :key="b.id" :session="b.session!" :booking="b" :now="now" show-date />
      </div>
    </template>
  </template>

  <v-snackbar :model-value="!!feedback" timeout="4000" location="top" @update:model-value="feedback = null">{{ feedback }}</v-snackbar>
</template>
