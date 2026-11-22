<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { userManager } from '@/auth/oidc'
import { queryClient } from '@/plugins/query'

const router = useRouter()
const failed = ref(false)

onMounted(async () => {
  try {
    const user = await userManager.signinRedirectCallback()
    queryClient.clear()
    const state = user.state as { returnTo?: string } | undefined
    await router.replace(state?.returnTo && state.returnTo !== '/callback' ? state.returnTo : '/')
  } catch {
    failed.value = true
  }
})
</script>

<template>
  <v-main class="d-flex align-center justify-center">
    <div class="text-center pa-8" role="status">
      <template v-if="!failed">
        <v-progress-circular indeterminate color="primary" size="32" width="3" />
        <p class="mt-4 text-medium">Abrindo a agenda do estúdio…</p>
      </template>
      <template v-else>
        <h1 class="display-2 mb-2">Não deu para entrar</h1>
        <p class="text-medium mb-6">O retorno do login veio incompleto ou expirou. Tente de novo.</p>
        <v-btn color="primary" to="/">Voltar ao início</v-btn>
      </template>
    </div>
  </v-main>
</template>
