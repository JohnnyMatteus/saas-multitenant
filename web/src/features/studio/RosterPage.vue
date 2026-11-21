<script setup lang="ts">
import { mdiArrowLeft, mdiArrowUp } from '@mdi/js'
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ApiError } from '@/api/client'
import { useCancelBooking, useRoster, useSession } from '@/api/queries'
import { wasPromoted, type Booking } from '@/api/schemas'
import StateBlock from '@/components/StateBlock.vue'
import MatRow from '@/components/signature/MatRow.vue'
import { useStudioClock } from '@/composables/useStudioClock'
import { formatInStudio, hhmm, timezoneLabel } from '@/lib/time'

const RECENT_MS = 15 * 60_000
const route = useRoute()
const id = computed(() => String(route.params.id))
const { me, now, timezone } = useStudioClock()
const session = useSession(id)
const roster = useRoster(id, () => session.isSuccess.value)
const cancel = useCancelBooking()

const isAdmin = computed(() => me.data.value?.role === 'studio_admin')
const confirmed = computed(() => (roster.data.value ?? []).filter((b) => b.status === 'confirmed'))
const waiting = computed(() => (roster.data.value ?? []).filter((b) => b.status === 'waitlisted'))
const started = computed(() => !!session.data.value && new Date(session.data.value.starts_at) <= now.value)
const ended = computed(() => !!session.data.value && new Date(session.data.value.ends_at) <= now.value)

const recentlyPromoted = (b: Booking) => wasPromoted(b) && now.value.getTime() - new Date(b.confirmed_at!).getTime() < RECENT_MS
const arriving = computed(() => confirmed.value.filter(recentlyPromoted).length)

const toCancel = ref<Booking | null>(null)
const feedback = ref<string | null>(null)
const pastDeadline = computed(() => !!session.data.value && new Date(session.data.value.cancellation_deadline) <= now.value)

async function confirmCancel() {
  const booking = toCancel.value
  if (!booking) return
  try {
    const result = await cancel.mutateAsync(booking.id)
    const promotedName = result.promoted ? waiting.value.find((w) => w.id === result.promoted!.id)?.member?.name : null
    feedback.value = promotedName
      ? `Reserva de ${booking.member?.name} cancelada. ${promotedName} saiu da fila e recebe a confirmação por e-mail.`
      : `Reserva de ${booking.member?.name} cancelada.`
  } catch (error) {
    feedback.value = error instanceof ApiError ? error.message : 'Não foi possível cancelar.'
  } finally {
    toCancel.value = null
  }
}

const forbidden = computed(() => roster.error.value instanceof ApiError && roster.error.value.status === 403)
</script>

<template>
  <v-btn variant="text" :prepend-icon="mdiArrowLeft" class="mb-4 px-2" height="36" :to="{ name: 'today' }">Voltar para hoje</v-btn>

  <StateBlock v-if="session.isPending.value" kind="loading" title="Abrindo a lista da aula…" />
  <StateBlock v-else-if="session.isError.value" kind="error" title="Aula não encontrada" :text="session.error.value?.message" @retry="session.refetch()" />

  <template v-else-if="session.data.value">
    <header class="roster-head">
      <p class="label text-medium mb-1 text-capitalize">
        {{ formatInStudio(session.data.value.starts_at, timezone, "EEEE, d 'de' MMMM") }} · {{ timezoneLabel(timezone) }}
      </p>
      <h1 class="display-1">{{ session.data.value.title }}</h1>
      <p class="text-medium mb-6">
        <span class="time">{{ hhmm(session.data.value.starts_at, timezone) }}–{{ hhmm(session.data.value.ends_at, timezone) }}</span>
        com {{ session.data.value.instructor.name }}
        <v-chip v-if="started && !ended" class="ml-2" color="warning">em andamento</v-chip>
        <v-chip v-else-if="ended" class="ml-2">encerrada</v-chip>
      </p>
      <MatRow
        :capacity="session.data.value.capacity"
        :confirmed="session.data.value.confirmed_count"
        :waitlist="session.data.value.waitlist_count"
        :arriving="arriving"
        :started="started && !ended"
        size="lg"
        caption
        data-testid="roster-mats"
      />
    </header>

    <StateBlock v-if="forbidden" title="Esta lista é do instrutor da aula" text="Instrutores veem a lista das próprias aulas; a recepção vê todas." />
    <StateBlock v-else-if="roster.isError.value" kind="error" title="Não conseguimos ler a lista" :text="roster.error.value?.message" @retry="roster.refetch()" />

    <div v-else class="roster">
      <section aria-labelledby="confirmed-title">
        <h2 id="confirmed-title" class="section-title mb-2">Confirmados</h2>
        <ul v-if="confirmed.length" class="people">
          <li v-for="b in confirmed" :key="b.id" class="person" :class="{ 'person--arrived': recentlyPromoted(b) }">
            <span class="person__mat person__mat--filled" aria-hidden="true" />
            <div class="flex-grow-1">
              <div class="person__name">{{ b.member?.name }}</div>
              <div v-if="wasPromoted(b)" class="label promoted">
                <v-icon :icon="mdiArrowUp" size="14" /> veio da fila às {{ hhmm(b.confirmed_at!, timezone) }}
              </div>
              <div v-else class="label text-medium">reservou em {{ formatInStudio(b.queued_at, timezone, "d/MM 'às' HH:mm") }}</div>
            </div>
            <v-btn v-if="isAdmin && !started" variant="text" color="error" size="small" height="36" @click="toCancel = b">cancelar reserva</v-btn>
          </li>
        </ul>
        <p v-else class="text-medium">Ninguém reservou ainda. Os tapetes estão todos livres.</p>
      </section>

      <section v-if="waiting.length || !ended" aria-labelledby="waiting-title" class="waiting">
        <h2 id="waiting-title" class="section-title mb-2">Lista de espera</h2>
        <ol v-if="waiting.length" class="people">
          <li v-for="(b, i) in waiting" :key="b.id" class="person">
            <span class="person__pos tnum" :aria-label="`${i + 1}º da fila`">{{ i + 1 }}º</span>
            <div class="flex-grow-1">
              <div class="person__name">{{ b.member?.name }}</div>
              <div class="label text-medium">na fila desde {{ formatInStudio(b.queued_at, timezone, "d/MM 'às' HH:mm") }}</div>
            </div>
            <v-btn v-if="isAdmin && !started" variant="text" color="error" size="small" height="36" @click="toCancel = b">tirar da fila</v-btn>
          </li>
        </ol>
        <p v-else class="text-medium">Fila vazia.</p>
      </section>
    </div>
  </template>

  <v-dialog :model-value="toCancel !== null" max-width="440" @update:model-value="toCancel = null">
    <v-card class="pa-6" color="surface">
      <h2 class="display-2 mb-2">{{ toCancel?.status === 'waitlisted' ? 'Tirar da fila?' : 'Cancelar esta reserva?' }}</h2>
      <p class="text-medium mb-2">{{ toCancel?.member?.name }} perde o lugar em {{ session.data.value?.title }}.</p>
      <p v-if="toCancel?.status === 'confirmed' && waiting.length" class="mb-2">
        {{ waiting[0]?.member?.name }}, primeiro da fila, será confirmado na hora e receberá um e-mail.
      </p>
      <v-alert v-if="pastDeadline && toCancel?.status === 'confirmed'" color="warning" density="compact" class="mb-2">
        O prazo do aluno já passou; como recepção, você ainda pode cancelar.
      </v-alert>
      <div class="d-flex justify-end ga-2 mt-4">
        <v-btn variant="text" @click="toCancel = null">Manter</v-btn>
        <v-btn color="error" :loading="cancel.isPending.value" @click="confirmCancel">Cancelar reserva</v-btn>
      </div>
    </v-card>
  </v-dialog>

  <v-snackbar :model-value="feedback !== null" :timeout="6000" @update:model-value="feedback = null">{{ feedback }}</v-snackbar>
</template>

<style scoped>
.roster-head {
  padding: 28px;
  border-radius: 24px;
  background: rgb(var(--v-theme-surface));
  margin-bottom: 28px;
}
.roster {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 32px;
}
.people {
  list-style: none;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.person {
  display: flex;
  align-items: center;
  gap: 16px;
  min-height: 64px;
  padding: 10px 16px;
  border-radius: 20px;
  background: rgb(var(--v-theme-surface));
}
.person--arrived {
  box-shadow: inset 0 0 0 1.5px rgb(var(--v-theme-success));
}
.person__name {
  font-weight: 500;
}
.person__mat {
  width: 8px;
  height: 24px;
  border-radius: 999px;
  flex-shrink: 0;
}
.person__mat--filled {
  background: rgb(var(--v-theme-primary));
}
.person__pos {
  width: 32px;
  height: 32px;
  border-radius: 999px;
  border: 1.5px dashed rgb(var(--v-theme-secondary));
  color: rgb(var(--v-theme-secondary));
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 600;
  flex-shrink: 0;
}
.promoted {
  color: rgb(var(--v-theme-success));
}
.waiting {
  padding-left: 32px;
  border-left: 1.5px dashed rgb(var(--v-theme-secondary));
}
@media (max-width: 900px) {
  .roster {
    grid-template-columns: 1fr;
  }
  .waiting {
    padding-left: 0;
    border-left: 0;
    padding-top: 20px;
    border-top: 1.5px dashed rgb(var(--v-theme-secondary));
  }
}
</style>
