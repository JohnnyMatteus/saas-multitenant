<script setup lang="ts">
import { computed } from 'vue'
import { useLogoUrl } from '@/api/queries'

const props = withDefaults(defineProps<{ name: string; size?: number }>(), { size: 40 })
const url = useLogoUrl()

const initials = computed(() =>
  props.name
    .replace(/\b(Exemplo|Estúdio|Studio|de|do|da)\b/gi, '')
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? '')
    .join(''),
)
</script>

<template>
  <img v-if="url" :src="url" :alt="`Logo do ${name}`" class="studio-logo" :style="{ height: `${size}px`, maxWidth: `${size * 3.5}px` }" />
  <span v-else class="studio-logo studio-logo--monogram font-display" :style="{ width: `${size}px`, height: `${size}px`, fontSize: `${size * 0.4}px` }" role="img" :aria-label="`${name}, sem logo cadastrado`">
    {{ initials }}
  </span>
</template>

<style scoped>
.studio-logo {
  display: block;
  object-fit: contain;
  border-radius: 12px;
}
.studio-logo--monogram {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  background: rgb(var(--v-theme-surface-variant));
  color: rgb(var(--v-theme-on-surface));
  font-weight: 600;
  flex-shrink: 0;
}
</style>
