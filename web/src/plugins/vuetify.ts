import { createVuetify, type ThemeDefinition } from 'vuetify'
import { aliases, mdi } from 'vuetify/iconsets/mdi-svg'
import { pt } from 'vuetify/locale'
import 'vuetify/styles'

// Tokens da direção de identidade (seção 3, "Respiro").
export const respiroLight: ThemeDefinition = {
  dark: false,
  colors: {
    background: '#F7F2EA',
    surface: '#FFFCF7',
    'surface-bright': '#FFFFFF',
    'surface-light': '#F2EBE1',
    'surface-variant': '#EDE4D8',
    'on-surface-variant': '#2A211C',
    'on-background': '#2A211C',
    'on-surface': '#2A211C',
    primary: '#A2482A',
    'on-primary': '#FFFFFF',
    secondary: '#47624F',
    'on-secondary': '#FFFFFF',
    accent: '#7A4E7E',
    'on-accent': '#FFFFFF',
    success: '#2F6B45',
    warning: '#8A5A00',
    error: '#B3261E',
    info: '#2D5F86',
    'mat-free': '#9C8B7C',
    'day-dawn': '#F7F2EA',
    'day-dusk': '#EDE3EE',
  },
  variables: {
    'border-color': '#2A211C',
    'border-opacity': 0.14,
    'high-emphasis-opacity': 0.96,
    'medium-emphasis-opacity': 0.72,
    'disabled-opacity': 0.42,
    'hover-opacity': 0.06,
    'focus-opacity': 0.12,
  },
}

export const respiroDark: ThemeDefinition = {
  dark: true,
  colors: {
    background: '#191512',
    surface: '#221D19',
    'surface-bright': '#2C2621',
    'surface-light': '#2A241F',
    'surface-variant': '#3A322B',
    'on-surface-variant': '#F1E8DE',
    'on-background': '#F1E8DE',
    'on-surface': '#F1E8DE',
    primary: '#F0A07E',
    'on-primary': '#3A1408',
    secondary: '#A9C4AF',
    'on-secondary': '#13261A',
    accent: '#CDA6D1',
    'on-accent': '#2E1631',
    success: '#8FD1A3',
    warning: '#F2C46B',
    error: '#FFB4AB',
    info: '#9CC7EC',
    'mat-free': '#8C7E72',
    'day-dawn': '#221D19',
    'day-dusk': '#2A2030',
  },
  variables: {
    'border-color': '#F1E8DE',
    'border-opacity': 0.16,
    'high-emphasis-opacity': 0.94,
    'medium-emphasis-opacity': 0.72,
    'disabled-opacity': 0.4,
    'hover-opacity': 0.08,
    'focus-opacity': 0.14,
  },
}

export default createVuetify({
  locale: { locale: 'pt', messages: { pt } },
  icons: { defaultSet: 'mdi', aliases, sets: { mdi } },
  theme: {
    defaultTheme: 'respiroLight',
    themes: { respiroLight, respiroDark },
  },
  defaults: {
    global: { ripple: false },
    VBtn: { rounded: 'pill', variant: 'flat', class: 'text-none', height: 44 },
    VCard: { rounded: 'xl', elevation: 0, variant: 'flat' },
    VSheet: { rounded: 'xl' },
    VChip: { rounded: 'pill', variant: 'tonal', size: 'small' },
    VTextField: { variant: 'outlined', rounded: 'lg', density: 'comfortable', color: 'primary' },
    VSelect: { variant: 'outlined', rounded: 'lg', density: 'comfortable', color: 'primary' },
    VList: { density: 'comfortable', bgColor: 'transparent' },
    VListItem: { rounded: 'lg', minHeight: 72 },
    VBottomNavigation: { grow: true, elevation: 0, height: 64, bgColor: 'surface' },
    VBottomSheet: { inset: true },
    VNavigationDrawer: { elevation: 0, color: 'background' },
    VAppBar: { flat: true, color: 'background', height: 64 },
    VAlert: { variant: 'tonal', rounded: 'xl', border: 'start' },
    VSnackbar: { rounded: 'pill', location: 'bottom center' },
  },
})
