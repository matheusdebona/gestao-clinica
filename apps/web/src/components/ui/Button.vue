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
    'inline-flex h-10 items-center justify-center gap-2 rounded-[12px] px-4 text-[15px] font-medium tracking-[-0.01em] transition-colors duration-150 disabled:cursor-not-allowed disabled:opacity-40',
    props.block && 'w-full',
    props.variant === 'primary' &&
      'bg-brand text-inverse hover:bg-brand-hover active:bg-brand-dark',
    props.variant === 'secondary' &&
      'glass-clear text-title hover:bg-surface/50 active:bg-surface/60',
    props.variant === 'ghost' &&
      'bg-transparent text-brand hover:bg-brand-light/70 active:bg-brand/10',
    props.variant === 'destructive' &&
      'glass-chip-danger text-danger hover:bg-danger/15 active:bg-danger/20',
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
