<script setup lang="ts">
import { computed } from 'vue'
import { ApiError } from '@/api/client'
import { useIssueWhatsAppCode, useRevokeWhatsAppLink, useWhatsAppLink } from '@/api/queries'
import { formatInStudio } from '@/lib/time'

const props = defineProps<{ timezone: string }>()

const status = useWhatsAppLink()
const issue = useIssueWhatsAppCode()
const revoke = useRevokeWhatsAppLink()

const code = computed(() => issue.data.value)
const error = computed(() => {
  const e = issue.error.value ?? revoke.error.value ?? status.error.value
  return e ? (e instanceof ApiError ? e.message : 'Não foi possível falar com o servidor.') : null
})
</script>

<template>
  <section aria-labelledby="wa-title" class="wa">
    <h2 id="wa-title" class="section-title mb-1">WhatsApp</h2>
    <p class="text-medium mb-3">Vincule seu telefone para reservar e cancelar pelo WhatsApp do estúdio.</p>

    <p v-if="status.isPending.value" class="text-medium" role="status">Verificando o vínculo…</p>
    <template v-else-if="status.data.value">
      <p v-if="status.data.value.linked" class="mb-3" data-testid="wa-linked">
        Telefone vinculado{{ status.data.value.linked_at ? ` em ${formatInStudio(status.data.value.linked_at, props.timezone, "dd/MM/yyyy HH:mm")}` : '' }}.
      </p>
      <v-btn v-if="status.data.value.linked" variant="outlined" :loading="revoke.isPending.value" @click="revoke.mutate()">
        Desvincular telefone
      </v-btn>
      <template v-else>
        <div v-if="code" class="wa__code" data-testid="wa-code" aria-live="polite">
          <span class="label text-medium">Envie este código ao WhatsApp do estúdio (vale 10 minutos)</span>
          <strong class="font-display wa__digits">{{ code.code }}</strong>
        </div>
        <v-btn variant="tonal" color="primary" class="mt-3" :loading="issue.isPending.value" @click="issue.mutate()">
          {{ code ? 'Gerar outro código' : 'Gerar código de vínculo' }}
        </v-btn>
      </template>
    </template>
    <p v-if="error" class="text-error mt-3" role="alert">{{ error }}</p>
  </section>
</template>

<style scoped>
.wa__code {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.wa__digits {
  font-size: 32px;
  letter-spacing: 0.2em;
}
</style>
