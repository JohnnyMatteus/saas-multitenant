<script setup lang="ts">
import { computed, ref } from 'vue'
import { ApiError } from '@/api/client'
import { useCreateLessonPackage, useLessonPackages, useStudioPackageOrders, useUpdateLessonPackage } from '@/api/queries'
import StateBlock from '@/components/StateBlock.vue'
import { useStudioClock } from '@/composables/useStudioClock'
import { formatBRL, parseReaisToCents } from '@/lib/money'
import { formatInStudio } from '@/lib/time'

const { timezone } = useStudioClock()
const packages = useLessonPackages()
const orders = useStudioPackageOrders()
const create = useCreateLessonPackage()
const update = useUpdateLessonPackage()

const name = ref('')
const lessons = ref('8')
const price = ref('')
const submitted = ref(false)

const priceCents = computed(() => parseReaisToCents(price.value))
const lessonCount = computed(() => {
  const n = Number(lessons.value)
  return Number.isInteger(n) && n >= 1 && n <= 500 ? n : null
})
const nameError = computed(() => (name.value.trim().length < 2 ? 'Dê um nome ao pacote' : null))
const formError = computed(() =>
  create.error.value instanceof ApiError ? create.error.value.message : update.error.value instanceof ApiError ? update.error.value.message : null,
)

async function add() {
  submitted.value = true
  if (nameError.value || priceCents.value === null || lessonCount.value === null) return
  await create.mutateAsync({ name: name.value.trim(), lesson_count: lessonCount.value, price_cents: priceCents.value })
  name.value = ''
  price.value = ''
  submitted.value = false
}
</script>

<template>
  <header class="mb-6">
    <p class="label text-medium mb-1">Pacotes</p>
    <h1 class="display-1">Pacotes de aulas</h1>
  </header>

  <section class="panel mb-6" aria-labelledby="new-title">
    <h2 id="new-title" class="section-title mb-4">Novo pacote</h2>
    <form novalidate class="form" @submit.prevent="add">
      <v-text-field v-model="name" label="Nome" :error-messages="submitted ? (nameError ?? undefined) : undefined" />
      <v-text-field v-model="lessons" label="Aulas" inputmode="numeric" :error-messages="submitted && lessonCount === null ? 'Entre 1 e 500' : undefined" />
      <v-text-field v-model="price" label="Preço (R$)" inputmode="decimal" :error-messages="submitted && priceCents === null ? 'Informe um valor, como 320,00' : undefined" />
      <v-btn color="primary" type="submit" :loading="create.isPending.value">Criar pacote</v-btn>
    </form>
    <v-alert v-if="formError" color="error" density="compact" class="mt-3">{{ formError }}</v-alert>
  </section>

  <StateBlock v-if="packages.isPending.value" kind="loading" title="Lendo os pacotes…" />
  <StateBlock v-else-if="packages.isError.value" kind="error" title="Não conseguimos ler os pacotes" :text="packages.error.value?.message" @retry="packages.refetch()" />
  <section v-else class="panel mb-6" aria-labelledby="catalog-title">
    <h2 id="catalog-title" class="section-title mb-2">Catálogo</h2>
    <p v-if="!packages.data.value?.length" class="text-medium">Nenhum pacote ainda.</p>
    <ul class="rows">
      <li v-for="p in packages.data.value" :key="p.id" class="row">
        <span>{{ p.name }} · {{ p.lesson_count }} aulas · {{ formatBRL(p.price_cents) }}</span>
        <v-switch
          :model-value="p.active"
          :label="p.active ? 'À venda' : 'Suspenso'"
          hide-details
          density="compact"
          :aria-label="`Pacote ${p.name} à venda`"
          @update:model-value="(v) => update.mutate({ id: p.id, input: { active: v === true } })"
        />
      </li>
    </ul>
  </section>

  <section class="panel" aria-labelledby="orders-title">
    <h2 id="orders-title" class="section-title mb-2">Pedidos</h2>
    <p class="text-medium mb-3">Um pedido vira "pago" quando o processador de pagamentos confirma o valor.</p>
    <p v-if="!orders.data.value?.length" class="text-medium">Nenhum pedido ainda.</p>
    <ul class="rows">
      <li v-for="o in orders.data.value" :key="o.id" class="row">
        <span>{{ o.member.name }} · {{ o.package.name }} · {{ formatBRL(o.amount_cents) }}
          <span class="label text-medium">{{ formatInStudio(o.created_at, timezone, 'dd/MM HH:mm') }}</span></span>
        <v-chip :color="o.status === 'paid' ? 'success' : undefined" size="small">{{ o.status === 'paid' ? 'Pago' : 'Aguardando' }}</v-chip>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.panel {
  padding: 24px;
  border-radius: 20px;
  background: rgb(var(--v-theme-surface));
  box-shadow: inset 0 0 0 1px rgba(var(--v-border-color), var(--v-border-opacity));
}
.form {
  display: grid;
  gap: 4px;
  max-width: 420px;
}
.rows {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 4px;
}
.row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 0;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
