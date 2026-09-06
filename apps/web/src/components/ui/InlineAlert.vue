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
    'text-[13px]',
    props.variant === 'error' && 'text-danger',
    props.variant === 'warning' && 'text-warning',
    props.variant === 'info' && 'text-muted',
  ),
)
</script>

<template>
  <p :class="classes" role="alert">
    <slot />
  </p>
</template>
