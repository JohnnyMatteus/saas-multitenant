<script setup lang="ts">
import { computed } from 'vue'
import type { ClassSession } from '@/api/schemas'
import MatRow from '@/components/signature/MatRow.vue'
import { hhmm } from '@/lib/time'

const props = defineProps<{ session: ClassSession; now: Date; canOpen: boolean; mine?: boolean; highlighted?: boolean }>()

const started = computed(() => new Date(props.session.starts_at) <= props.now)
const ended = computed(() => new Date(props.session.ends_at) <= props.now)
</script>

<template>
  <article class="row" :class="{ 'row--ended': ended, 'row--highlighted': highlighted }" :data-session="session.id">
    <div class="row__time">
      <span class="time row__start">{{ hhmm(session.starts_at, session.timezone) }}</span>
      <span class="label tnum text-medium">{{ hhmm(session.ends_at, session.timezone) }}</span>
    </div>
    <div class="row__body">
      <h3 class="row__title font-display">{{ session.title }}</h3>
      <div class="label text-medium">
        com {{ session.instructor.name }}
        <v-chip v-if="mine" color="primary" class="ml-1" size="x-small">sua aula</v-chip>
      </div>
    </div>
    <div class="row__mats">
      <MatRow :capacity="session.capacity" :confirmed="session.confirmed_count" :waitlist="session.waitlist_count" :started="started && !ended" caption />
      <span v-if="ended" class="label text-medium">encerrada</span>
    </div>
    <div class="row__action">
      <v-btn v-if="canOpen" variant="tonal" color="primary" size="small" height="36" :to="{ name: 'roster', params: { id: session.id } }">ver lista</v-btn>
    </div>
  </article>
</template>

<style scoped>
.row {
  display: grid;
  grid-template-columns: 64px minmax(160px, 1.2fr) minmax(180px, 1fr) auto;
  align-items: center;
  gap: 20px;
  min-height: 72px;
  padding: 12px 20px;
  border-radius: 20px;
  background: rgb(var(--v-theme-surface));
  transition: box-shadow var(--respiro-fast) ease;
}
.row--ended {
  background: transparent;
  box-shadow: inset 0 0 0 1px rgba(var(--v-border-color), var(--v-border-opacity));
}
.row--highlighted {
  box-shadow: 0 0 0 2px rgb(var(--v-theme-primary));
}
.row__time {
  display: flex;
  flex-direction: column;
}
.row__start {
  font-size: 18px;
}
.row__title {
  font-size: 19px;
  line-height: 24px;
  font-weight: 500;
  font-variation-settings: 'SOFT' 100;
}
.row__mats {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
@media (max-width: 760px) {
  .row {
    grid-template-columns: 56px 1fr;
    row-gap: 10px;
  }
  .row__mats {
    grid-column: 2;
  }
  .row__action {
    grid-column: 2;
  }
}
</style>
