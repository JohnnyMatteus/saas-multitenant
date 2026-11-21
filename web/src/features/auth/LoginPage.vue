<script setup lang="ts">
import { ref } from 'vue'
import { login } from '@/auth/oidc'
import MatRow from '@/components/signature/MatRow.vue'

const loading = ref(false)

function enter() {
  loading.value = true
  void login('/')
}
</script>

<template>
  <v-main class="login">
    <div class="login__inner">
      <p class="label text-medium mb-6">Respiro · agenda de estúdios de yoga e pilates</p>
      <h1 class="login__title font-display">A aula medida em tapetes, não em vagas.</h1>
      <div class="login__mats my-8">
        <MatRow :capacity="12" :confirmed="9" :waitlist="2" size="lg" caption />
      </div>
      <p class="login__lede text-medium">
        O estúdio monta a grade no próprio fuso; o aluno reserva, entra na fila e é avisado quando a vaga abre.
      </p>
      <v-btn color="primary" size="large" class="mt-8" :loading="loading" @click="enter">Entrar com a conta do estúdio</v-btn>
      <p class="label text-medium mt-4">O login acontece no Keycloak do estúdio (OIDC com PKCE).</p>
    </div>
  </v-main>
</template>

<style scoped>
.login {
  min-height: 100vh;
  background: rgb(var(--v-theme-background));
}
.login__inner {
  max-width: 640px;
  margin: 0 auto;
  padding: 18vh 20px 48px;
}
.login__title {
  font-size: clamp(34px, 6vw, 52px);
  line-height: 1.08;
  font-weight: 600;
}
.login__lede {
  font-size: 17px;
  line-height: 26px;
  max-width: 480px;
}
</style>
