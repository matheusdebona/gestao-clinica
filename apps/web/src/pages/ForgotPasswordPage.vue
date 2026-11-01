<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthShell from '@/components/patterns/AuthShell.vue'
import ActionBar from '@/components/ui/ActionBar.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import { FORGOT_PASSWORD_SENT_MESSAGE } from '@/lib/password-reset'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const form = reactive({
  email: typeof route.query.email === 'string' ? route.query.email : '',
})
const error = ref('')
const loading = ref(false)
const sent = ref(false)

async function submit() {
  error.value = ''
  if (!form.email.trim()) {
    error.value = 'Informe o e-mail.'
    return
  }
  loading.value = true
  try {
    await auth.forgotPassword(form.email.trim())
    sent.value = true
  } catch (err) {
    if (err instanceof ApiError && err.status === 422) {
      error.value = err.first('email') || err.message
    } else if (err instanceof ApiError && err.status === 429) {
      toast.error('Muitas tentativas. Aguarde alguns minutos e tente de novo.')
    } else {
      toast.error('Não foi possível conectar. Tente de novo.')
    }
  } finally {
    loading.value = false
  }
}

function goToLogin(withEmail = false) {
  const email = form.email.trim()
  void router.push(withEmail && email ? { path: '/login', query: { email } } : '/login')
}
</script>

<template>
  <AuthShell
    title="Esqueceu a senha?"
    :subtitle="sent ? undefined : 'Informe o e-mail de acesso. Enviaremos uma senha temporária.'"
  >
    <div v-if="sent" class="mt-5 flex flex-col gap-4">
      <Banner variant="success" title="E-mail enviado">
        {{ FORGOT_PASSWORD_SENT_MESSAGE }}
      </Banner>
      <p class="text-[13px] text-muted">
        A senha temporária vale por 60 minutos. Entre com ela e, em seguida, crie uma nova senha.
      </p>
      <ActionBar>
        <Button class="sm:min-w-[200px]" @click="goToLogin(true)">
          Ir para o login
        </Button>
      </ActionBar>
    </div>

    <form v-else class="mt-5 flex flex-col gap-4" novalidate @submit.prevent="submit">
      <FormField label="E-mail" :error="error" html-for="forgot-email">
        <template #default="{ invalid }">
          <Input
            id="forgot-email"
            v-model="form.email"
            type="email"
            autocomplete="email"
            inputmode="email"
            :invalid="invalid"
          />
        </template>
      </FormField>
      <ActionBar class="mt-1">
        <template #negative>
          <Button variant="secondary" @click="goToLogin()">
            Voltar ao login
          </Button>
        </template>
        <Button type="submit" :loading="loading">
          Esqueci a senha
        </Button>
      </ActionBar>
    </form>
  </AuthShell>
</template>
