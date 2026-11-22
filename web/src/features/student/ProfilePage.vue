<script setup lang="ts">
import { mdiLogout } from '@mdi/js'
import { logout } from '@/auth/oidc'
import { useStudioClock } from '@/composables/useStudioClock'
import { timezoneLabel } from '@/lib/time'
import { useUiStore } from '@/stores/ui'

const { me, timezone } = useStudioClock()
const ui = useUiStore()
</script>

<template>
  <h1 class="display-1 mb-4">Perfil</h1>
  <div v-if="me.data.value" class="mb-6">
    <p class="section-title mb-0">{{ me.data.value.name }}</p>
    <p class="text-medium mb-0">{{ me.data.value.email }}</p>
    <p class="label text-medium mt-2">{{ me.data.value.studio.name }} · {{ timezoneLabel(timezone) }}</p>
  </div>
  <div class="d-flex flex-column ga-3">
    <v-btn variant="tonal" size="large" @click="ui.toggleTheme()">{{ ui.isDark ? 'Usar tema claro' : 'Usar tema escuro' }}</v-btn>
    <v-btn variant="outlined" size="large" :prepend-icon="mdiLogout" @click="logout()">Sair</v-btn>
  </div>
</template>
