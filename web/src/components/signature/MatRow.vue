<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { matRowCaption, matRowLabel } from './matRowLabel'

/**
 * Elemento-assinatura do Respiro: a lotação desenhada como uma fileira de
 * tapetes. Substitui "12/15" e barras de progresso em toda a interface.
 */
const props = withDefaults(
  defineProps<{
    capacity: number
    confirmed: number
    waitlist?: number
    size?: 'panel' | 'app' | 'lg'
    started?: boolean
    /** Quantos dos últimos tapetes ocupados vieram da fila há pouco (entram deslizando). */
    arriving?: number
    /** Posição do aluno logado na fila, destacada e numerada. */
    youPosition?: number | null
    youConfirmed?: boolean
    caption?: boolean
    maxWaitlist?: number
  }>(),
  { waitlist: 0, size: 'panel', started: false, arriving: 0, youPosition: null, youConfirmed: false, caption: false, maxWaitlist: 6 },
)

const occupied = computed(() => Math.min(props.confirmed, props.capacity))
const shownWaitlist = computed(() => Math.min(props.waitlist, props.maxWaitlist))
const hiddenWaitlist = computed(() => props.waitlist - shownWaitlist.value)
const numbered = computed(() => props.size !== 'panel')

// Promoção ao vivo: ocupados sobem e a fila desce entre duas leituras.
const liveArriving = ref(0)
let timer: ReturnType<typeof setTimeout> | undefined
watch(
  () => [props.confirmed, props.waitlist] as const,
  ([confirmed, waitlist], [prevConfirmed, prevWaitlist]) => {
    const promoted = Math.min(confirmed - prevConfirmed, prevWaitlist - waitlist)
    if (promoted > 0) {
      liveArriving.value = promoted
      clearTimeout(timer)
      timer = setTimeout(() => (liveArriving.value = 0), 2400)
    }
  },
)
onBeforeUnmount(() => clearTimeout(timer))

const arrivingCount = computed(() => Math.max(props.arriving, liveArriving.value))

const mats = computed(() =>
  Array.from({ length: props.capacity }, (_, i) => ({
    filled: i < occupied.value,
    arriving: i < occupied.value && i >= occupied.value - arrivingCount.value,
    you: props.youConfirmed && i === occupied.value - 1,
  })),
)

const label = computed(() => matRowLabel(props.capacity, props.confirmed, props.waitlist, props.started))
const captionText = computed(() => matRowCaption(props.capacity, props.confirmed, props.waitlist, props.started))
</script>

<template>
  <div class="mat-row" :class="[`mat-row--${size}`, { 'mat-row--started': started }]">
    <div class="mat-row__mats" role="img" :aria-label="label">
      <span
        v-for="(mat, i) in mats"
        :key="`m${i}`"
        class="mat"
        :class="{ 'mat--filled': mat.filled, 'mat--arriving': mat.arriving, 'mat--you': mat.you }"
        data-testid="mat"
      />
      <template v-if="shownWaitlist > 0">
        <span class="mat-row__gap" aria-hidden="true" />
        <span v-for="n in shownWaitlist" :key="`w${n}`" class="mat-slot">
          <span class="mat mat--waiting" :class="{ 'mat--you': youPosition === n }" data-testid="mat-waiting" />
          <span v-if="numbered" class="mat-slot__n tnum" :class="{ 'mat-slot__n--you': youPosition === n }" aria-hidden="true">{{ n }}</span>
        </span>
        <span v-if="hiddenWaitlist > 0" class="mat-row__more label tnum" aria-hidden="true">+{{ hiddenWaitlist }}</span>
      </template>
    </div>
    <span v-if="caption" class="mat-row__caption label" aria-hidden="true">{{ captionText }}</span>
  </div>
</template>

<style scoped>
.mat-row {
  --mat-w: 6px;
  --mat-h: 18px;
  --mat-gap: 4px;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  min-width: 0;
}
.mat-row--app {
  --mat-w: 8px;
  --mat-h: 24px;
  --mat-gap: 5px;
}
.mat-row--lg {
  --mat-w: 12px;
  --mat-h: 36px;
  --mat-gap: 7px;
}
.mat-row__mats {
  display: inline-flex;
  align-items: flex-start;
  gap: var(--mat-gap);
  flex-wrap: wrap;
  row-gap: 8px;
}
.mat {
  display: block;
  width: var(--mat-w);
  height: var(--mat-h);
  border-radius: 999px;
  border: 1.5px solid rgb(var(--v-theme-mat-free));
  background: transparent;
  box-sizing: border-box;
  transition: background-color var(--respiro-fast) ease, border-color var(--respiro-fast) ease;
}
.mat--filled {
  background: rgb(var(--v-theme-primary));
  border-color: rgb(var(--v-theme-primary));
}
.mat--waiting {
  border: 1.5px dashed rgb(var(--v-theme-secondary));
}
.mat--you {
  box-shadow: 0 0 0 2px rgb(var(--v-theme-background)), 0 0 0 3.5px rgb(var(--v-theme-on-surface));
}
.mat--waiting.mat--you {
  background: rgba(var(--v-theme-secondary), 0.28);
}
/* O tapete tracejado da fila desliza para dentro da fileira e preenche (240 ms). */
.mat--arriving {
  animation: mat-arrive 240ms ease-out both, mat-settle 1800ms 240ms ease-out;
}
@keyframes mat-arrive {
  from {
    transform: translateX(calc((var(--mat-w) + var(--mat-gap)) * 3));
    background: transparent;
    border-style: dashed;
    border-color: rgb(var(--v-theme-secondary));
  }
  to {
    transform: translateX(0);
  }
}
@keyframes mat-settle {
  from {
    box-shadow: 0 0 0 3px rgba(var(--v-theme-success), 0.45);
  }
  to {
    box-shadow: 0 0 0 0 rgba(var(--v-theme-success), 0);
  }
}
.mat-row__gap {
  width: calc(var(--mat-gap) * 2.5);
}
.mat-slot {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}
.mat-slot__n {
  font-size: 11px;
  line-height: 12px;
  font-weight: 600;
  color: rgb(var(--v-theme-secondary));
}
.mat-slot__n--you {
  color: rgb(var(--v-theme-on-surface));
}
.mat-row__more {
  color: rgb(var(--v-theme-secondary));
  align-self: center;
}
.mat-row--started .mat-row__mats {
  opacity: 0.4;
}
.mat-row__caption {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  white-space: nowrap;
}
@media (prefers-reduced-motion: reduce) {
  .mat--arriving {
    animation: none;
  }
}
</style>
