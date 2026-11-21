<script setup lang="ts">
/** Estado vazio, de carregamento ou de erro, sempre com texto do domínio. */
withDefaults(defineProps<{ kind?: 'empty' | 'loading' | 'error'; title: string; text?: string; compact?: boolean }>(), {
  kind: 'empty',
  text: undefined,
  compact: false,
})
defineEmits<{ retry: [] }>()
</script>

<template>
  <div class="state" :class="[`state--${kind}`, { 'state--compact': compact }]" :role="kind === 'error' ? 'alert' : 'status'">
    <slot name="art">
      <v-progress-circular v-if="kind === 'loading'" indeterminate color="primary" size="28" width="3" class="mb-4" />
    </slot>
    <h2 class="section-title mb-1">{{ title }}</h2>
    <p v-if="text" class="text-medium state__text">{{ text }}</p>
    <div class="mt-4">
      <slot>
        <v-btn v-if="kind === 'error'" variant="tonal" color="primary" @click="$emit('retry')">Tentar de novo</v-btn>
      </slot>
    </div>
  </div>
</template>

<style scoped>
.state {
  padding: 40px 24px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.state--compact {
  padding: 20px 12px;
}
.state__text {
  max-width: 420px;
}
.state--error .section-title {
  color: rgb(var(--v-theme-error));
}
</style>
