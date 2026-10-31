<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import AuthShell from '@/components/patterns/AuthShell.vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import PasswordInput from '@/components/ui/PasswordInput.vue'
import { PASSWORD_RULES_HINT, validateNewPassword } from '@/lib/password-reset'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const form = reactive({
  temporary_password: '',
  password: '',
  password_confirmation: '',
})
const errors = reactive<Record<string, string>>({})
const loading = ref(false)

function clearErrors() {
  for (const key of Object.keys(errors)) {
    delete errors[key]
  }
}

async function submit() {
  clearErrors()
  const local = validateNewPassword(form)
  if (Object.keys(local).length) {
    Object.assign(errors, local)
    return
  }
  loading.value = true
  try {
    await auth.changeTemporaryPassword({ ...form })
    toast.success('Senha alterada')
    await router.replace('/')
  } catch (error) {
    if (error instanceof ApiError && error.status === 422) {
      for (const [field, messages] of Object.entries(error.errors)) {
        errors[field] = messages[0] ?? error.message
      }
      if (!Object.keys(error.errors).length) {
        toast.error(error.message)
      }
    } else if (error instanceof ApiError) {
      toast.error(error.message)
    } else {
      toast.error('Não foi possível conectar. Tente de novo.')
    }
  } finally {
    loading.value = false
  }
}

async function leave() {
  await auth.logout()
  await router.replace('/login')
}
</script>

<template>
  <AuthShell
    title="Redefinir senha"
    subtitle="Você entrou com uma senha temporária. Crie uma nova senha para continuar."
  >
    <form class="mt-6 flex flex-col gap-4" novalidate @submit.prevent="submit">
      <FormField
        label="Senha temporária"
        hint="A senha que chegou no seu e-mail."
        :error="errors.temporary_password"
        html-for="reset-temporary"
      >
        <template #default="{ invalid }">
          <PasswordInput
            id="reset-temporary"
            v-model="form.temporary_password"
            autocomplete="current-password"
            :invalid="invalid"
          />
        </template>
      </FormField>
      <FormField
        label="Nova senha"
        :hint="PASSWORD_RULES_HINT"
        :error="errors.password"
        html-for="reset-password"
      >
        <template #default="{ invalid }">
          <PasswordInput
            id="reset-password"
            v-model="form.password"
            autocomplete="new-password"
            :invalid="invalid"
          />
        </template>
      </FormField>
      <FormField
        label="Confirmar nova senha"
        :error="errors.password_confirmation"
        html-for="reset-password-2"
      >
        <template #default="{ invalid }">
          <PasswordInput
            id="reset-password-2"
            v-model="form.password_confirmation"
            autocomplete="new-password"
            :invalid="invalid"
          />
        </template>
      </FormField>
      <Button type="submit" block :loading="loading">
        Salvar nova senha
      </Button>
    </form>
    <p class="mt-5 text-[13px] text-muted">
      Não é você?
      <Button variant="ghost" class="!inline !h-auto !px-1 !py-0" @click="leave">
        Sair
      </Button>
    </p>
  </AuthShell>
</template>
