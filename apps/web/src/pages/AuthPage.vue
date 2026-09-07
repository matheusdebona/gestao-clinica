<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import authClinic from '@/assets/auth-clinic.jpg'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import PasswordInput from '@/components/ui/PasswordInput.vue'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const mode = ref<'login' | 'register'>('login')
const loading = ref(false)

const loginForm = reactive({
  email: '',
  password: '',
})

const registerForm = reactive({
  clinic_name: '',
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const loginErrors = reactive<Record<string, string>>({})
const registerErrors = reactive<Record<string, string>>({})

function clearErrors(bag: Record<string, string>) {
  for (const key of Object.keys(bag)) {
    delete bag[key]
  }
}

function applyErrors(bag: Record<string, string>, error: unknown) {
  clearErrors(bag)
  if (!(error instanceof ApiError)) {
    toast.error('Não foi possível conectar. Tente de novo.')
    return
  }
  if (error.status === 422) {
    for (const [field, messages] of Object.entries(error.errors)) {
      bag[field] = messages[0] ?? error.message
    }
    if (!Object.keys(error.errors).length) {
      toast.error(error.message)
    }
    return
  }
  toast.error(error.message)
}

async function submitLogin() {
  clearErrors(loginErrors)
  loading.value = true
  try {
    await auth.login(loginForm.email, loginForm.password)
    await router.push('/')
  } catch (error) {
    applyErrors(loginErrors, error)
  } finally {
    loading.value = false
  }
}

async function submitRegister() {
  clearErrors(registerErrors)
  if (registerForm.password !== registerForm.password_confirmation) {
    registerErrors.password_confirmation = 'As senhas não coincidem.'
    return
  }
  loading.value = true
  try {
    await auth.register({ ...registerForm })
    toast.success('Clínica criada')
    await router.push('/')
  } catch (error) {
    applyErrors(registerErrors, error)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="relative flex min-h-dvh items-center justify-center px-4 py-[5dvh]">
    <div class="absolute top-4 right-4 z-10">
      <ThemeToggle />
    </div>
    <div
      class="glass-regular auth-card h-[90dvh] w-full max-w-[920px] overflow-hidden rounded-[22px]"
      :class="{ register: mode === 'register' }"
    >
      <div class="auth-image">
        <img
          :src="authClinic"
          alt=""
          class="size-full object-cover"
        >
        <div class="absolute inset-0 bg-sidebar/40" />
        <p class="absolute bottom-5 left-5 right-5 text-[15px] font-medium tracking-[-0.02em] text-inverse">
          Gestão da clínica, com calma.
        </p>
      </div>

      <section class="auth-login flex min-h-0 flex-col justify-center overflow-y-auto px-6 py-8 md:px-10">
        <h1 class="text-[28px] font-semibold tracking-[-0.03em] text-title">Entrar</h1>
        <p class="mt-1 text-[15px] text-muted">Use o e-mail da clínica.</p>
        <form class="mt-6 flex flex-col gap-4" @submit.prevent="submitLogin">
          <FormField label="E-mail" :error="loginErrors.email" html-for="login-email">
            <template #default="{ invalid }">
              <Input
                id="login-email"
                v-model="loginForm.email"
                type="email"
                autocomplete="email"
                :invalid="invalid"
              />
            </template>
          </FormField>
          <FormField label="Senha" :error="loginErrors.password" html-for="login-password">
            <template #default="{ invalid }">
              <PasswordInput
                id="login-password"
                v-model="loginForm.password"
                autocomplete="current-password"
                :invalid="invalid"
              />
            </template>
          </FormField>
          <Button type="submit" block :loading="loading && mode === 'login'">
            Continuar
          </Button>
        </form>
        <p class="mt-5 text-[13px] text-muted">
          Ainda sem clínica?
          <Button variant="ghost" class="!inline !h-auto !px-1 !py-0" @click="mode = 'register'">
            Cadastrar-se
          </Button>
        </p>
      </section>

      <section class="auth-register flex min-h-0 flex-col justify-center overflow-y-auto px-6 py-8 md:px-10">
        <h1 class="text-[28px] font-semibold tracking-[-0.03em] text-title">Cadastrar</h1>
        <p class="mt-1 text-[15px] text-muted">Abre a clínica e o primeiro acesso.</p>
        <form class="mt-6 flex flex-col gap-4" @submit.prevent="submitRegister">
          <FormField label="Nome da clínica" :error="registerErrors.clinic_name" html-for="reg-clinic">
            <template #default="{ invalid }">
              <Input id="reg-clinic" v-model="registerForm.clinic_name" type="text" :invalid="invalid" />
            </template>
          </FormField>
          <FormField label="Seu nome" :error="registerErrors.name" html-for="reg-name">
            <template #default="{ invalid }">
              <Input id="reg-name" v-model="registerForm.name" type="text" :invalid="invalid" />
            </template>
          </FormField>
          <FormField label="E-mail" :error="registerErrors.email" html-for="reg-email">
            <template #default="{ invalid }">
              <Input
                id="reg-email"
                v-model="registerForm.email"
                type="email"
                autocomplete="email"
                :invalid="invalid"
              />
            </template>
          </FormField>
          <FormField
            label="Senha"
            hint="Mínimo 10 caracteres, com maiúscula, número e símbolo."
            :error="registerErrors.password"
            html-for="reg-password"
          >
            <template #default="{ invalid }">
              <PasswordInput
                id="reg-password"
                v-model="registerForm.password"
                autocomplete="new-password"
                :invalid="invalid"
              />
            </template>
          </FormField>
          <FormField
            label="Confirmar senha"
            :error="registerErrors.password_confirmation"
            html-for="reg-password-2"
          >
            <template #default="{ invalid }">
              <PasswordInput
                id="reg-password-2"
                v-model="registerForm.password_confirmation"
                autocomplete="new-password"
                :invalid="invalid"
              />
            </template>
          </FormField>
          <Button type="submit" block :loading="loading && mode === 'register'">
            Criar clínica
          </Button>
        </form>
        <p class="mt-5 text-[13px] text-muted">
          Já tem acesso?
          <Button variant="ghost" class="!inline !h-auto !px-1 !py-0" @click="mode = 'login'">
            Entrar
          </Button>
        </p>
      </section>
    </div>
  </div>
</template>

<style scoped>
.auth-card {
  display: grid;
  grid-template-rows: minmax(0, 1fr);
  grid-template-columns: 1fr;
}

.auth-image {
  display: none;
}

.auth-login,
.auth-register {
  grid-row: 1;
  grid-column: 1;
  min-height: 0;
  align-self: stretch;
}

.auth-card:not(.register) .auth-register,
.auth-card.register .auth-login {
  display: none;
}

@media (min-width: 768px) {
  .auth-card {
    position: relative;
    grid-template-rows: minmax(0, 1fr);
    grid-template-columns: 1fr 1fr;
  }

  .auth-image {
    display: block;
    position: absolute;
    inset-block: 0;
    left: 0;
    z-index: 2;
    width: 50%;
    height: auto;
    grid-row: 1;
    grid-column: 1;
    transition: transform 0.55s cubic-bezier(0.22, 1, 0.36, 1);
  }

  .auth-card.register .auth-image {
    transform: translateX(100%);
  }

  .auth-login,
  .auth-register {
    grid-row: 1;
    min-height: 0;
    height: 100%;
    align-self: stretch;
  }

  .auth-login {
    grid-column: 2;
    display: flex;
  }

  .auth-register {
    grid-column: 1;
    display: flex;
  }

  .auth-card:not(.register) .auth-register,
  .auth-card.register .auth-login {
    display: flex;
    visibility: hidden;
  }
}

@media (prefers-reduced-motion: reduce) {
  .auth-image {
    transition: none;
  }
}
</style>
