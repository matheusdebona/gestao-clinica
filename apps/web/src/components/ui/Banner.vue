<script setup lang="ts">
import { cn } from '@/lib/cn'
import { AlertTriangle, CheckCircle2, Info, XCircle } from '@lucide/vue'
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

const icon = computed(() => {
  switch (props.variant) {
    case 'success':
      return CheckCircle2
    case 'warning':
      return AlertTriangle
    case 'danger':
      return XCircle
    default:
      return Info
  }
})

const classes = computed(() =>
  cn(
    'flex gap-3 rounded-lg border px-4 py-3 text-sm',
    props.variant === 'info' && 'border-brand/20 bg-brand-light text-brand-dark',
    props.variant === 'success' && 'border-success/20 bg-success-light text-success',
    props.variant === 'warning' && 'border-warning/30 bg-warning-light text-warning',
    props.variant === 'danger' && 'border-danger/20 bg-danger-light text-danger',
  ),
)
</script>

<template>
  <div :class="classes" role="status">
    <component :is="icon" class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
    <div class="min-w-0">
      <p v-if="title" class="font-semibold">{{ title }}</p>
      <p :class="title ? 'mt-0.5 opacity-90' : ''">
        <slot />
      </p>
    </div>
  </div>
</template>
