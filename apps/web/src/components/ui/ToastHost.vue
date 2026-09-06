<script setup lang="ts">
import { cn } from '@/lib/cn'
import { useToastStore, type ToastVariant } from '@/stores/toast'
import { storeToRefs } from 'pinia'
import { computed } from 'vue'

const toast = useToastStore()
const { items } = storeToRefs(toast)

function classesFor(variant: ToastVariant) {
  return cn(
    'pointer-events-auto flex w-auto max-w-[min(100%,360px)] items-center gap-3 rounded-full border border-white/40 bg-title/90 px-4 py-2.5 text-[13px] text-inverse shadow-floating backdrop-blur-md',
    variant === 'success' && 'bg-[color-mix(in_srgb,var(--sv-text-title)_90%,var(--sv-success))]',
    variant === 'error' && 'bg-[color-mix(in_srgb,var(--sv-text-title)_90%,var(--sv-danger))]',
  )
}

const hasItems = computed(() => items.value.length > 0)
</script>

<template>
  <div
    v-if="hasItems"
    class="pointer-events-none fixed inset-x-0 top-0 z-[60] flex flex-col items-center gap-2 p-4"
    aria-live="polite"
  >
    <button
      v-for="item in items"
      :key="item.id"
      type="button"
      :class="classesFor(item.variant)"
      role="status"
      @click="toast.dismiss(item.id)"
    >
      <span class="min-w-0 font-medium tracking-[-0.01em]">{{ item.message }}</span>
    </button>
  </div>
</template>
