<script setup lang="ts">
import { computed, ref } from 'vue'
import { ApiError } from '@/api/client'
import { useLessonPackages, useMyPackageOrders, useOrderPackage } from '@/api/queries'
import StateBlock from '@/components/StateBlock.vue'
import { useStudioClock } from '@/composables/useStudioClock'
import { formatBRL } from '@/lib/money'
import { formatInStudio } from '@/lib/time'

const { timezone } = useStudioClock()
const packages = useLessonPackages()
const orders = useMyPackageOrders()
const order = useOrderPackage()
const feedback = ref<string | null>(null)
const busyId = ref<string | null>(null)

const lastOrder = computed(() => order.data.value ?? null)

async function buy(packageId: string) {
  busyId.value = packageId
  feedback.value = null
  try {
    await order.mutateAsync(packageId)
  } catch (error) {
    feedback.value = error instanceof ApiError ? error.message : 'Não foi possível criar o pedido.'
  } finally {
    busyId.value = null
  }
}
</script>

<template>
  <h1 class="display-1 mb-1">Pacotes</h1>
  <p class="text-medium mb-4">Escolha um pacote e faça o pagamento com a referência do pedido.</p>

  <StateBlock v-if="packages.isPending.value" kind="loading" title="Buscando os pacotes…" />
  <StateBlock v-else-if="packages.isError.value" kind="error" title="Não conseguimos ler os pacotes" :text="packages.error.value?.message" @retry="packages.refetch()" />
  <StateBlock v-else-if="!packages.data.value?.length" title="Nenhum pacote à venda" text="O estúdio ainda não publicou pacotes." />

  <ul v-else class="packages" aria-label="Pacotes à venda">
    <li v-for="p in packages.data.value" :key="p.id" class="package">
      <div>
        <p class="section-title mb-0">{{ p.name }}</p>
        <p class="text-medium mb-0">{{ p.lesson_count }} aulas · {{ formatBRL(p.price_cents) }}</p>
      </div>
      <v-btn color="primary" variant="tonal" :loading="busyId === p.id" @click="buy(p.id)">Comprar</v-btn>
    </li>
  </ul>

  <v-alert v-if="feedback" color="error" density="compact" class="mt-3">{{ feedback }}</v-alert>
  <v-alert v-if="lastOrder" color="info" density="compact" class="mt-3" data-testid="order-reference">
    Pedido criado. Referência de pagamento: <strong>{{ lastOrder.payment_id }}</strong>. O pedido passa a "pago" quando o pagamento for confirmado.
  </v-alert>

  <section v-if="orders.data.value?.length" class="mt-6" aria-labelledby="orders-title">
    <h2 id="orders-title" class="section-title mb-2">Meus pedidos</h2>
    <ul class="packages">
      <li v-for="o in orders.data.value" :key="o.id" class="package">
        <div>
          <p class="mb-0">{{ o.package.name }} · {{ formatBRL(o.amount_cents) }}</p>
          <p class="label text-medium mb-0">{{ formatInStudio(o.created_at, timezone, 'dd/MM/yyyy HH:mm') }} · {{ o.payment_id }}</p>
        </div>
        <v-chip :color="o.status === 'paid' ? 'success' : undefined" size="small" :data-testid="`status-${o.status}`">
          {{ o.status === 'paid' ? 'Pago' : 'Aguardando pagamento' }}
        </v-chip>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.packages {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 8px;
}
.package {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 16px;
  box-shadow: inset 0 0 0 1px rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
