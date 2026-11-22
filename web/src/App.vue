<script setup lang="ts">
import { watchEffect } from 'vue'
import { useTheme } from 'vuetify'
import { useUiStore } from '@/stores/ui'

const ui = useUiStore()
const theme = useTheme()

watchEffect(() => {
  theme.global.name.value = ui.themeName
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content', ui.isDark ? '#191512' : '#F7F2EA')
})
</script>

<template>
  <v-app>
    <router-view />
  </v-app>
</template>
