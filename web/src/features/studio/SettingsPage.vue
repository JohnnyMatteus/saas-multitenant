<script setup lang="ts">
import { mdiDownloadOutline, mdiUploadOutline } from '@mdi/js'
import { computed, ref, watch } from 'vue'
import { ApiError } from '@/api/client'
import { api } from '@/api/endpoints'
import { useStudio, useUpdateStudio, useUploadLogo } from '@/api/queries'
import StateBlock from '@/components/StateBlock.vue'
import StudioLogo from '@/components/StudioLogo.vue'
import MatRow from '@/components/signature/MatRow.vue'
import { filenameFrom, saveBlob } from '@/lib/download'
import { timezoneLabel, windowLabel } from '@/lib/time'

const MAX_LOGO_KB = 512
const studio = useStudio()
const update = useUpdateStudio()
const upload = useUploadLogo()

const name = ref('')
const windowMinutes = ref(720)
watch(
  () => studio.data.value,
  (s) => {
    if (s) {
      name.value = s.name
      windowMinutes.value = s.cancellation_window_minutes
    }
  },
  { immediate: true },
)

const windows = [0, 60, 120, 240, 360, 720, 1440, 2880].map((m) => ({ value: m, title: windowLabel(m) }))
const dirty = computed(
  () => !!studio.data.value && (name.value.trim() !== studio.data.value.name || windowMinutes.value !== studio.data.value.cancellation_window_minutes),
)
const nameError = computed(() => (name.value.trim().length < 2 ? 'O nome precisa de pelo menos 2 letras' : null))
const saved = ref(false)

async function save() {
  if (nameError.value) return
  await update.mutateAsync({ name: name.value.trim(), cancellation_window_minutes: windowMinutes.value })
  saved.value = true
}

const fileInput = ref<HTMLInputElement | null>(null)
const logoError = ref<string | null>(null)
async function onFile(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  logoError.value = null
  if (!file) return
  if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
    logoError.value = 'Use PNG, JPG ou WebP. SVG fica de fora por segurança.'
    return
  }
  if (file.size > MAX_LOGO_KB * 1024) {
    logoError.value = `O arquivo passa de ${MAX_LOGO_KB} KB.`
    return
  }
  try {
    await upload.mutateAsync(file)
  } catch (error) {
    logoError.value = error instanceof ApiError ? error.message : 'Não foi possível enviar o logo.'
  } finally {
    if (fileInput.value) fileInput.value.value = ''
  }
}
const exporting = ref(false)
const exportError = ref<string | null>(null)
async function exportData() {
  exporting.value = true
  exportError.value = null
  try {
    const { blob, disposition } = await api.exportStudio()
    saveBlob(blob, filenameFrom(disposition, 'estudio.json'))
  } catch (error) {
    exportError.value = error instanceof ApiError ? error.message : 'Não foi possível exportar os dados.'
  } finally {
    exporting.value = false
  }
}
const updateError = computed(() => (update.error.value instanceof ApiError ? update.error.value.message : null))
</script>

<template>
  <header class="mb-6">
    <p class="label text-medium mb-1">Ajustes</p>
    <h1 class="display-1">O estúdio</h1>
  </header>

  <StateBlock v-if="studio.isPending.value" kind="loading" title="Lendo os ajustes do estúdio…" />
  <StateBlock v-else-if="studio.isError.value" kind="error" title="Não conseguimos ler os ajustes" :text="studio.error.value?.message" @retry="studio.refetch()" />

  <div v-else-if="studio.data.value" class="settings">
    <section class="panel" aria-labelledby="logo-title">
      <h2 id="logo-title" class="section-title mb-1">Logo</h2>
      <p class="text-medium mb-4">Aparece no topo do painel e do app do aluno. A paleta do Respiro não muda com ele.</p>
      <div class="logo-preview">
        <div class="logo-preview__bar">
          <StudioLogo :name="studio.data.value.name" :size="40" />
          <span class="font-display">{{ studio.data.value.name }}</span>
        </div>
        <MatRow :capacity="10" :confirmed="7" size="app" class="mt-3" />
      </div>
      <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/webp" class="d-none" aria-label="Arquivo do logo" data-testid="logo-input" @change="onFile" />
      <div class="d-flex align-center ga-3 mt-4 flex-wrap">
        <v-btn variant="tonal" color="primary" :prepend-icon="mdiUploadOutline" :loading="upload.isPending.value" @click="fileInput?.click()">
          {{ studio.data.value.has_logo ? 'Trocar logo' : 'Enviar logo' }}
        </v-btn>
        <span class="label text-medium">PNG, JPG ou WebP, até {{ MAX_LOGO_KB }} KB.</span>
      </div>
      <v-alert v-if="logoError" color="error" density="compact" class="mt-3">{{ logoError }}</v-alert>
      <v-alert v-else-if="upload.isSuccess.value" color="success" density="compact" class="mt-3">Logo atualizado para todo o estúdio.</v-alert>
    </section>

    <section class="panel" aria-labelledby="rules-title">
      <h2 id="rules-title" class="section-title mb-4">Nome e regras</h2>
      <form novalidate @submit.prevent="save">
        <v-text-field v-model="name" label="Nome do estúdio" :error-messages="nameError ?? undefined" />
        <v-select v-model="windowMinutes" :items="windows" label="O aluno cancela sem custo até" />
        <p class="label text-medium mb-4">
          Depois do prazo, só a recepção cancela. Quem estiver na fila é promovido na hora, nos dois casos.
        </p>
        <v-text-field :model-value="timezoneLabel(studio.data.value.timezone)" label="Fuso do estúdio" readonly hint="As aulas já geradas usam este fuso; trocar exige migrar a grade." persistent-hint />
        <v-alert v-if="updateError" color="error" density="compact" class="mt-3">{{ updateError }}</v-alert>
        <div class="d-flex justify-end mt-6">
          <v-btn color="primary" type="submit" :disabled="!dirty || !!nameError" :loading="update.isPending.value">Salvar ajustes</v-btn>
        </div>
      </form>
    </section>

    <section class="panel" aria-labelledby="export-title">
      <h2 id="export-title" class="section-title mb-1">Exportar dados</h2>
      <p class="text-medium mb-4">
        Baixa um arquivo JSON com a grade, os alunos (nome e e-mail), as reservas e os pacotes do estúdio. Guarde com o mesmo cuidado dos dados dos alunos. Limite de 5 por hora.
      </p>
      <v-btn variant="tonal" color="primary" :prepend-icon="mdiDownloadOutline" :loading="exporting" @click="exportData">Exportar dados do estúdio</v-btn>
      <v-alert v-if="exportError" color="error" density="compact" class="mt-3">{{ exportError }}</v-alert>
    </section>
  </div>

  <v-snackbar v-model="saved" color="success" :timeout="4000">Ajustes salvos.</v-snackbar>
</template>

<style scoped>
.settings {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 24px;
  align-items: start;
}
.panel {
  padding: 24px;
  border-radius: 24px;
  background: rgb(var(--v-theme-surface));
}
.logo-preview {
  padding: 16px 20px;
  border-radius: 20px;
  background: rgb(var(--v-theme-background));
}
.logo-preview__bar {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 18px;
}
@media (max-width: 900px) {
  .settings {
    grid-template-columns: 1fr;
  }
}
</style>
