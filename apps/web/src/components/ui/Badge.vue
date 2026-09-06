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
    'glass-chip inline-flex items-center rounded-full px-2 py-0.5 text-[12px] font-medium',
    props.variant === 'success' && 'glass-chip-success text-success',
    props.variant === 'purple' && 'glass-chip-brand text-brand',
    props.variant === 'muted' && 'text-muted',
    props.variant === 'warning' && 'glass-chip-warning text-warning',
    props.variant === 'danger' && 'glass-chip-danger text-danger',
  ),
)
</script>

<template>
  <span :class="classes">
    <slot />
  </span>
</template>
