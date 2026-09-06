import { ofetch } from 'ofetch'
import { ApiError } from '@/types/user'
import { clearToken, getToken } from './auth-storage'

const baseURL = import.meta.env.VITE_API_URL || '/api/v1'

export const api = ofetch.create({
  baseURL,
  headers: {
    Accept: 'application/json',
  },
  onRequest({ options }) {
    const token = getToken()
    const headers = new Headers(options.headers)
    if (token) {
      headers.set('Authorization', `Bearer ${token}`)
    }
    headers.set('Accept', 'application/json')
    options.headers = headers
  },
  async onResponseError({ response }) {
    if (response.status === 401) {
      clearToken()
      const path = window.location.pathname
      if (path !== '/login' && path !== '/dev/ui') {
        const redirect = encodeURIComponent(`${path}${window.location.search}`)
        window.location.assign(`/login?redirect=${redirect}`)
      }
    }

    const payload = (await response._data) as {
      message?: string
      errors?: Record<string, string[]>
    } | undefined

    throw new ApiError(
      payload?.message || 'Não foi possível concluir a solicitação.',
      response.status,
      payload?.errors ?? {},
    )
  },
})
