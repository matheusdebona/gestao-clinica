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
    'inline-flex items-center rounded-full px-2.5 py-0.5 font-semibold tracking-[0.02em] uppercase',
    props.variant === 'success' && 'bg-success-light text-success',
    props.variant === 'purple' && 'bg-brand-light text-badge-purple',
    props.variant === 'muted' && 'bg-surface-muted text-muted',
    props.variant === 'warning' && 'bg-warning-light text-warning',
    props.variant === 'danger' && 'bg-danger-light text-danger',
  ),
)
</script>

<template>
  <span :class="classes" :style="{ fontSize: 'var(--sv-text-badge)' }">
    <slot />
  </span>
</template>
