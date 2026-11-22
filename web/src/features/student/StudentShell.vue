<script setup lang="ts">
import { mdiAccountOutline, mdiCalendarBlankOutline, mdiCalendarCheckOutline } from '@mdi/js'
import StudioLogo from '@/components/StudioLogo.vue'
import { useStudioClock } from '@/composables/useStudioClock'
import { timezoneLabel } from '@/lib/time'

const { me, timezone } = useStudioClock()
const nav = [
  { to: { name: 'agenda' }, icon: mdiCalendarBlankOutline, label: 'Agenda' },
  { to: { name: 'my-classes' }, icon: mdiCalendarCheckOutline, label: 'Minhas aulas' },
  { to: { name: 'profile' }, icon: mdiAccountOutline, label: 'Perfil' },
]
</script>

<template>
  <v-app-bar class="student-bar">
    <div class="student-bar__inner">
      <StudioLogo :name="me.data.value?.studio.name ?? 'Estúdio'" :size="36" />
      <div class="student-bar__text">
        <span class="font-display student-bar__name">{{ me.data.value?.studio.name }}</span>
        <span class="label text-medium">{{ timezoneLabel(timezone) }}</span>
      </div>
    </div>
  </v-app-bar>

  <v-main class="student-main">
    <div class="student-main__inner">
      <router-view />
    </div>
  </v-main>

  <v-bottom-navigation class="student-nav" aria-label="Navegação do app">
    <v-btn v-for="item in nav" :key="item.label" :to="item.to" stacked class="text-none" height="64" rounded="0" :prepend-icon="item.icon">
      <span class="label">{{ item.label }}</span>
    </v-btn>
  </v-bottom-navigation>
</template>

<style scoped>
.student-bar {
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.student-bar__inner {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  max-width: 560px;
  margin: 0 auto;
  padding: 0 20px;
}
.student-bar__text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.student-bar__name {
  font-size: 17px;
  font-weight: 600;
  line-height: 22px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.student-main {
  background: rgb(var(--v-theme-background));
  min-height: 100vh;
}
.student-main__inner {
  max-width: 560px;
  margin: 0 auto;
  padding: 16px 20px 96px;
  font-size: 16px;
  line-height: 24px;
}
.student-nav {
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.student-nav :deep(.v-btn--active) {
  color: rgb(var(--v-theme-primary));
}
</style>
