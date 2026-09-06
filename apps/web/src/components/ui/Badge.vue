<script setup lang="ts">
import { cn } from '@/lib/cn'
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    variant?: 'success' | 'purple' | 'muted' | 'warning' | 'danger'
  }>(),
  { variant: 'muted' },
)

const classes = computed(() =>
  cn(
    'inline-flex items-center rounded-full px-2 py-0.5 text-[12px] font-medium',
    props.variant === 'success' && 'bg-success-light text-success',
    props.variant === 'purple' && 'bg-brand-light text-brand',
    props.variant === 'muted' && 'bg-surface-muted text-muted',
    props.variant === 'warning' && 'bg-warning-light text-warning',
    props.variant === 'danger' && 'bg-danger-light text-danger',
  ),
)
</script>

<template>
  <span :class="classes">
    <slot />
  </span>
</template>
