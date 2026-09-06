<script setup lang="ts">
import { cn } from '@/lib/cn'
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    variant?: 'info' | 'warning' | 'danger' | 'success'
    title?: string
  }>(),
  {
    variant: 'info',
    title: '',
  },
)

const labelClass = computed(() => {
  if (props.variant === 'success') return 'text-success'
  if (props.variant === 'warning') return 'text-warning'
  if (props.variant === 'danger') return 'text-danger'
  return 'text-brand'
})
</script>

<template>
  <div
    :class="cn('flex gap-3 border-b border-border-divider py-3 last:border-b-0 last:pb-0 first:pt-0')"
    role="status"
  >
    <div class="min-w-0">
      <p v-if="title" :class="['text-[15px] font-medium', labelClass]">{{ title }}</p>
      <p class="text-[15px] text-muted">
        <slot />
      </p>
    </div>
  </div>
</template>
