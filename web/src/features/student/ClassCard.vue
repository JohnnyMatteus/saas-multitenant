<script setup lang="ts">
import { computed } from 'vue'
import type { Booking, ClassSession } from '@/api/schemas'
import { wasPromoted } from '@/api/schemas'
import MatRow from '@/components/signature/MatRow.vue'
import { deadlinePhrase, formatInStudio, hhmm, ordinal } from '@/lib/time'

const props = defineProps<{ session: ClassSession; booking?: Booking; now: Date; busy?: boolean; showDate?: boolean }>()
defineEmits<{ book: []; cancel: [] }>()

const started = computed(() => new Date(props.session.starts_at) <= props.now)
const ended = computed(() => new Date(props.session.ends_at) <= props.now)
const status = computed(() => props.booking?.status)
const canCancel = computed(() => {
  if (started.value) return false
  // Sair da fila não tem prazo; a vaga confirmada respeita a janela do estúdio.
  return status.value === 'waitlisted' || new Date(props.session.cancellation_deadline) > props.now
})
const wouldBe = computed(() => props.session.waitlist_count + 1)
const minutes = computed(() => Math.round((new Date(props.session.ends_at).getTime() - new Date(props.session.starts_at).getTime()) / 60_000))
</script>

<template>
  <article class="card" :class="{ 'card--mine': status === 'confirmed', 'card--queued': status === 'waitlisted', 'card--started': started }">
    <div class="card__top">
      <div class="card__time">
        <span class="card__hour tnum">{{ hhmm(session.starts_at, session.timezone) }}</span>
        <span class="label text-medium tnum">{{ minutes }} min</span>
      </div>
      <div class="card__info">
        <p v-if="showDate" class="label text-medium text-capitalize mb-0">{{ formatInStudio(session.starts_at, session.timezone, "EEEE, d 'de' MMM") }}</p>
        <h3 class="card__title font-display">{{ session.title }}</h3>
        <p class="label text-medium">com {{ session.instructor.name }}</p>
      </div>
    </div>

    <MatRow
      class="mt-3"
      :capacity="session.capacity"
      :confirmed="session.confirmed_count"
      :waitlist="session.waitlist_count"
      :you-position="status === 'waitlisted' ? booking?.waitlist_position : null"
      :you-confirmed="status === 'confirmed'"
      :started="started && !ended"
      size="app"
      caption
    />

    <div class="card__actions">
      <template v-if="ended">
        <span class="label text-medium">{{ status === 'confirmed' ? 'Você esteve nesta aula' : 'Aula encerrada' }}</span>
      </template>
      <template v-else-if="status === 'confirmed'">
        <div class="flex-grow-1">
          <v-chip color="success" variant="tonal">{{ booking && wasPromoted(booking) ? 'Vaga confirmada: você saiu da fila' : 'Você vai' }}</v-chip>
          <p v-if="canCancel" class="label text-medium mt-1">cancele sem custo até {{ deadlinePhrase(session.cancellation_deadline, session.timezone, now) }}</p>
          <p v-else-if="!started" class="label warning-text mt-1">o prazo para cancelar terminou</p>
        </div>
        <v-btn v-if="canCancel" variant="outlined" color="primary" :loading="busy" @click="$emit('cancel')">Cancelar</v-btn>
      </template>
      <template v-else-if="status === 'waitlisted'">
        <div class="flex-grow-1">
          <v-chip color="secondary" variant="tonal">Na fila: você é o {{ ordinal(booking?.waitlist_position ?? 1) }}</v-chip>
          <p class="label text-medium mt-1">Se alguém cancelar, a vaga é sua e avisamos por e-mail.</p>
        </div>
        <v-btn v-if="!started" variant="text" color="secondary" :loading="busy" @click="$emit('cancel')">Sair da fila</v-btn>
      </template>
      <template v-else-if="started">
        <span class="label text-medium">em andamento</span>
      </template>
      <template v-else-if="session.available_spots > 0">
        <v-btn color="primary" block size="large" :loading="busy" @click="$emit('book')">Reservar</v-btn>
      </template>
      <template v-else>
        <v-btn color="secondary" variant="tonal" block size="large" :loading="busy" @click="$emit('book')">
          Entrar na fila (você seria o {{ ordinal(wouldBe) }})
        </v-btn>
      </template>
    </div>
  </article>
</template>

<style scoped>
.card {
  padding: 18px 20px;
  border-radius: 20px;
  background: rgb(var(--v-theme-surface));
}
.card--mine {
  box-shadow: inset 0 0 0 1.5px rgba(var(--v-theme-success), 0.6);
}
.card--queued {
  box-shadow: inset 0 0 0 1.5px rgba(var(--v-theme-secondary), 0.6);
}
.card__top {
  display: flex;
  gap: 16px;
}
.card__time {
  display: flex;
  flex-direction: column;
  min-width: 64px;
}
.card__hour {
  font-size: 24px;
  line-height: 30px;
  font-weight: 600;
}
.card__title {
  font-size: 22px;
  line-height: 28px;
  font-weight: 600;
  font-variation-settings: 'SOFT' 100;
}
.card__actions {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 14px;
  min-height: 48px;
}
.card__actions :deep(.v-btn) {
  min-height: 48px;
}
.warning-text {
  color: rgb(var(--v-theme-warning));
}
</style>
