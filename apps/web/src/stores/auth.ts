import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '@/lib/api'
import { clearToken, getToken, setToken } from '@/lib/auth-storage'
import type { AuthResponse, AuthUser } from '@/types/user'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(getToken())
  const user = ref<AuthUser | null>(null)
  const ready = ref(false)

  const isAuthenticated = computed(() => Boolean(token.value))
  const clinicName = computed(() => user.value?.clinic?.name ?? '')
  const permissions = computed(() => user.value?.permissions ?? [])
  const mustChangePassword = computed(() => Boolean(user.value?.must_change_password))

  function can(permission: string): boolean {
    return permissions.value.includes(permission)
  }

  function applySession(payload: AuthResponse) {
    token.value = payload.token
    user.value = payload.user
    setToken(payload.token)
  }

  async function login(email: string, password: string) {
    const payload = await api<AuthResponse>('/auth/login', {
      method: 'POST',
      body: { email, password },
    })
    applySession(payload)
  }

  async function register(input: {
    clinic_name: string
    name: string
    email: string
    password: string
    password_confirmation: string
  }) {
    const payload = await api<AuthResponse>('/auth/register', {
      method: 'POST',
      body: input,
    })
    applySession(payload)
  }

  async function forgotPassword(email: string) {
    return api<{ message: string }>('/auth/forgot-password', {
      method: 'POST',
      body: { email },
    })
  }

  async function changeTemporaryPassword(input: {
    temporary_password: string
    password: string
    password_confirmation: string
  }) {
    const payload = await api<{ message: string; user: AuthUser }>('/auth/change-temporary-password', {
      method: 'POST',
      body: input,
    })
    user.value = payload.user
  }

  async function hydrate() {
    if (!token.value) {
      user.value = null
      ready.value = true
      return
    }

    try {
      const payload = await api<{ data?: AuthUser } & AuthUser>('/auth/me')
      user.value = payload.data ?? payload
    } catch {
      token.value = null
      user.value = null
      clearToken()
    } finally {
      ready.value = true
    }
  }

  async function logout() {
    try {
      if (token.value) {
        await api('/auth/logout', { method: 'POST' })
      }
    } finally {
      token.value = null
      user.value = null
      clearToken()
    }
  }

  return {
    token,
    user,
    ready,
    isAuthenticated,
    clinicName,
    permissions,
    mustChangePassword,
    can,
    login,
    register,
    forgotPassword,
    changeTemporaryPassword,
    hydrate,
    logout,
  }
})
