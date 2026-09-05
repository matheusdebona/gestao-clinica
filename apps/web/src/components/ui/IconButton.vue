<script setup lang="ts">
import { cn } from '@/lib/cn'
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    type?: 'button' | 'submit' | 'reset'
    disabled?: boolean
    label: string
  }>(),
  {
    type: 'button',
    disabled: false,
  },
)

const classes = computed(() =>
  cn(
    'inline-flex size-10 items-center justify-center rounded-full text-body transition-colors duration-150 hover:bg-surface-muted disabled:cursor-not-allowed disabled:opacity-50',
  ),
)
</script>

<template>
  <button :type="type" :class="classes" :disabled="disabled" :aria-label="label">
    <slot />
  </button>
</template>
