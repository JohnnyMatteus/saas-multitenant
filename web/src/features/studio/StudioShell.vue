<script setup lang="ts">
import { mdiCalendarTodayOutline, mdiCalendarWeekOutline, mdiCogOutline, mdiLogout, mdiWeatherNight, mdiWhiteBalanceSunny } from '@mdi/js'
import { computed } from 'vue'
import { useDisplay } from 'vuetify'
import { logout } from '@/auth/oidc'
import StudioLogo from '@/components/StudioLogo.vue'
import { useStudioClock } from '@/composables/useStudioClock'
import { useUiStore } from '@/stores/ui'
import CreateSeriesSheet from './CreateSeriesSheet.vue'

const ui = useUiStore()
const { mdAndUp } = useDisplay()
const { me } = useStudioClock()

const isAdmin = computed(() => me.data.value?.role === 'studio_admin')
const nav = computed(() => [
  { to: { name: 'today' }, icon: mdiCalendarTodayOutline, label: 'Hoje' },
  { to: { name: 'week' }, icon: mdiCalendarWeekOutline, label: 'Semana' },
  ...(isAdmin.value ? [{ to: { name: 'settings' }, icon: mdiCogOutline, label: 'Ajustes' }] : []),
])
const roleLabel = computed(() => (isAdmin.value ? 'admin do estúdio' : 'instrutor'))
</script>

<template>
  <v-navigation-drawer v-if="mdAndUp" permanent :width="84" class="rail" aria-label="Navegação do estúdio">
    <div class="rail__inner">
      <div class="rail__logo">
        <StudioLogo :name="me.data.value?.studio.name ?? 'Estúdio'" :size="44" />
      </div>
      <nav class="rail__nav">
        <router-link v-for="item in nav" :key="item.label" v-slot="{ href, navigate, isActive }" :to="item.to" custom>
          <a :href="href" class="rail__item" :class="{ 'rail__item--active': isActive }" :aria-current="isActive ? 'page' : undefined" @click="navigate">
            <span class="rail__icon"><v-icon :icon="item.icon" size="20" /></span>
            <span class="rail__label label">{{ item.label }}</span>
          </a>
        </router-link>
      </nav>
      <div class="rail__bottom">
        <button type="button" class="rail__item" :aria-label="ui.isDark ? 'Usar tema claro' : 'Usar tema escuro'" @click="ui.toggleTheme()">
          <span class="rail__icon"><v-icon :icon="ui.isDark ? mdiWhiteBalanceSunny : mdiWeatherNight" size="20" /></span>
          <span class="rail__label label">{{ ui.isDark ? 'Claro' : 'Escuro' }}</span>
        </button>
        <v-menu location="end bottom">
          <template #activator="{ props: menu }">
            <button type="button" class="rail__item" v-bind="menu" :aria-label="`Conta de ${me.data.value?.name ?? ''}`">
              <span class="rail__avatar font-display">{{ me.data.value?.name?.[0] ?? '·' }}</span>
            </button>
          </template>
          <v-card min-width="240" class="pa-2" color="surface">
            <div class="px-3 pt-2 pb-3">
              <div class="font-weight-medium">{{ me.data.value?.name }}</div>
              <div class="label text-medium">{{ me.data.value?.email }} · {{ roleLabel }}</div>
            </div>
            <v-list-item :prepend-icon="mdiLogout" title="Sair" min-height="44" @click="logout()" />
          </v-card>
        </v-menu>
      </div>
    </div>
  </v-navigation-drawer>

  <v-main class="studio-main">
    <div class="studio-main__inner">
      <router-view />
    </div>
  </v-main>

  <v-bottom-navigation v-if="!mdAndUp" aria-label="Navegação do estúdio">
    <v-btn v-for="item in nav" :key="item.label" :to="item.to" :prepend-icon="item.icon" stacked class="text-none" height="64" rounded="0">
      {{ item.label }}
    </v-btn>
    <v-btn stacked class="text-none" height="64" rounded="0" :prepend-icon="ui.isDark ? mdiWhiteBalanceSunny : mdiWeatherNight" @click="ui.toggleTheme()">Tema</v-btn>
    <v-btn stacked class="text-none" height="64" rounded="0" :prepend-icon="mdiLogout" @click="logout()">Sair</v-btn>
  </v-bottom-navigation>

  <CreateSeriesSheet v-if="isAdmin" v-model="ui.createSeriesOpen" />
</template>

<style scoped>
.rail {
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)) !important;
}
.rail__inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  height: 100%;
  padding: 16px 0;
}
.rail__logo {
  margin-bottom: 28px;
  display: flex;
  justify-content: center;
}
.rail__nav,
.rail__bottom {
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: center;
}
.rail__bottom {
  margin-top: auto;
}
.rail__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  width: 68px;
  padding: 4px 0;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  text-decoration: none;
  background: none;
  border: 0;
  cursor: pointer;
}
.rail__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 52px;
  height: 32px;
  border-radius: 999px;
  transition: background-color var(--respiro-fast) ease;
}
.rail__item:hover .rail__icon {
  background: rgba(var(--v-theme-on-surface), var(--v-hover-opacity));
}
.rail__item--active {
  color: rgb(var(--v-theme-on-surface));
}
.rail__item--active .rail__icon {
  background: rgba(var(--v-theme-primary), 0.16);
  color: rgb(var(--v-theme-primary));
}
.rail__label {
  font-size: 12px;
}
.rail__avatar {
  display: inline-flex;
  width: 36px;
  height: 36px;
  border-radius: 999px;
  align-items: center;
  justify-content: center;
  background: rgb(var(--v-theme-surface-variant));
  color: rgb(var(--v-theme-on-surface));
  font-weight: 600;
}
.studio-main {
  background: rgb(var(--v-theme-background));
  min-height: 100vh;
}
.studio-main__inner {
  max-width: 1240px;
  margin: 0 auto;
  padding: 24px 32px 48px;
}
@media (max-width: 959px) {
  .studio-main__inner {
    padding: 16px 20px 96px;
  }
}
</style>
