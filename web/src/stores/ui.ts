import { usePreferredDark, useStorage } from '@vueuse/core'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

export type ThemePreference = 'system' | 'light' | 'dark'

export const useUiStore = defineStore('ui', () => {
  const themePreference = useStorage<ThemePreference>('respiro.theme', 'system')
  const prefersDark = usePreferredDark()
  const themeName = computed(() => {
    const dark = themePreference.value === 'dark' || (themePreference.value === 'system' && prefersDark.value)
    return dark ? 'respiroDark' : 'respiroLight'
  })
  const isDark = computed(() => themeName.value === 'respiroDark')

  const createSeriesOpen = ref(false)

  function toggleTheme() {
    themePreference.value = isDark.value ? 'light' : 'dark'
  }

  return { themePreference, themeName, isDark, toggleTheme, createSeriesOpen }
})
