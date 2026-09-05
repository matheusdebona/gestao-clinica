<script setup lang="ts">
import { cn } from '@/lib/cn'
import { useToastStore, type ToastVariant } from '@/stores/toast'
import { CheckCircle2, Info, X, XCircle } from '@lucide/vue'
import { storeToRefs } from 'pinia'
import { computed } from 'vue'

const toast = useToastStore()
const { items } = storeToRefs(toast)

function iconFor(variant: ToastVariant) {
  if (variant === 'success') return CheckCircle2
  if (variant === 'error') return XCircle
  return Info
}

function classesFor(variant: ToastVariant) {
  return cn(
    'pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-floating',
    variant === 'success' && 'border-success/20 bg-surface text-success',
    variant === 'error' && 'border-danger/20 bg-surface text-danger',
    variant === 'info' && 'border-brand/20 bg-surface text-brand-dark',
  )
}

const hasItems = computed(() => items.value.length > 0)
</script>

<template>
  <div
    v-if="hasItems"
    class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:items-end"
    aria-live="polite"
  >
    <div
      v-for="item in items"
      :key="item.id"
      :class="classesFor(item.variant)"
      role="status"
    >
      <component :is="iconFor(item.variant)" class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
      <p class="min-w-0 flex-1 font-medium text-title">{{ item.message }}</p>
      <button
        type="button"
        class="rounded-full p-1 text-muted hover:bg-surface-muted"
        aria-label="Fechar"
        @click="toast.dismiss(item.id)"
      >
        <X class="size-4" />
      </button>
    </div>
  </div>
</template>
