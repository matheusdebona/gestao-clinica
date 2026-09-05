<script setup lang="ts">
import { cn } from '@/lib/cn'
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    variant?: 'error' | 'warning' | 'info'
  }>(),
  { variant: 'error' },
)

const classes = computed(() =>
  cn(
    'rounded-md px-3 py-2 text-sm',
    props.variant === 'error' && 'bg-danger-light text-danger',
    props.variant === 'warning' && 'bg-warning-light text-warning',
    props.variant === 'info' && 'bg-brand-light text-brand-dark',
  ),
)
</script>

<template>
  <div :class="classes" role="alert">
    <slot />
  </div>
</template>
