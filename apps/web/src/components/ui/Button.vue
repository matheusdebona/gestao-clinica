<script setup lang="ts">
import { cn } from '@/lib/cn'
import { LoaderCircle } from '@lucide/vue'
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    variant?: 'primary' | 'secondary' | 'ghost' | 'destructive'
    type?: 'button' | 'submit' | 'reset'
    loading?: boolean
    disabled?: boolean
    block?: boolean
  }>(),
  {
    variant: 'primary',
    type: 'button',
    loading: false,
    disabled: false,
    block: false,
  },
)

const classes = computed(() =>
  cn(
    'inline-flex items-center justify-center gap-2 rounded-md px-4 py-2.5 text-sm font-semibold transition-colors duration-150 disabled:cursor-not-allowed disabled:opacity-50',
    props.block && 'w-full',
    props.variant === 'primary' &&
      'bg-brand text-inverse hover:bg-brand-hover active:bg-brand-dark',
    props.variant === 'secondary' &&
      'bg-brand-light text-brand hover:bg-brand/15 active:bg-brand/20',
    props.variant === 'ghost' &&
      'bg-transparent text-body hover:bg-surface-muted active:bg-border-subtle',
    props.variant === 'destructive' &&
      'bg-danger text-inverse hover:bg-danger/90 active:bg-danger/80',
  ),
)
</script>

<template>
  <button
    :type="type"
    :class="classes"
    :disabled="disabled || loading"
  >
    <LoaderCircle v-if="loading" class="size-4 animate-spin" aria-hidden="true" />
    <slot />
  </button>
</template>
