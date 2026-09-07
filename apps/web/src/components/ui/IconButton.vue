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
    'glass-clear inline-flex size-9 items-center justify-center rounded-full text-title transition-colors duration-150 hover:bg-surface/50 disabled:cursor-not-allowed disabled:opacity-40',
  ),
)
</script>

<template>
  <button :type="type" :class="classes" :disabled="disabled" :aria-label="label">
    <slot />
  </button>
</template>
