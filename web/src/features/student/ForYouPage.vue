<script setup lang="ts">
import { mdiCompassOutline } from '@mdi/js'
import StateBlock from '@/components/StateBlock.vue'
</script>

<template>
  <h1 class="display-1 mb-1">Para você</h1>
  <p class="text-medium mb-4">Sugestões de aula a partir do que você costuma fazer.</p>

  <section class="plum" aria-labelledby="foryou-title">
    <StateBlock title="Ainda sem sugestões" text="As recomendações aparecem aqui quando o serviço de sugestões estiver ligado ao seu estúdio. Enquanto isso, a agenda mostra tudo que está aberto.">
      <template #art><v-icon :icon="mdiCompassOutline" size="40" color="accent" class="mb-3" /></template>
      <v-btn variant="tonal" color="accent" :to="{ name: 'agenda' }">Ver a agenda</v-btn>
    </StateBlock>
  </section>
</template>

<style scoped>
.plum {
  border-radius: 20px;
  background: rgb(var(--v-theme-surface));
  box-shadow: inset 0 0 0 1.5px rgba(var(--v-theme-accent), 0.5);
}
</style>
