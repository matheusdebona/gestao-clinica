export type ToastVariant = 'success' | 'error' | 'info'

export interface ToastItem {
  id: string
  message: string
  variant: ToastVariant
}

import { defineStore } from 'pinia'
import { ref } from 'vue'

let toastSeq = 0

export const useToastStore = defineStore('toast', () => {
  const items = ref<ToastItem[]>([])

  function push(message: string, variant: ToastVariant = 'info', durationMs = 3200) {
    const id = `toast-${++toastSeq}`
    items.value = [...items.value, { id, message, variant }]
    window.setTimeout(() => dismiss(id), durationMs)
  }

  function success(message: string) {
    push(message, 'success')
  }

  function error(message: string) {
    push(message, 'error')
  }

  function info(message: string) {
    push(message, 'info')
  }

  function dismiss(id: string) {
    items.value = items.value.filter((t) => t.id !== id)
  }

  return { items, push, success, error, info, dismiss }
})
