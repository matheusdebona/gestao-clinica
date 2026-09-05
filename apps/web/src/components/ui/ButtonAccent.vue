<script setup lang="ts">
import { cn } from '@/lib/cn'
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    type?: 'button' | 'submit' | 'reset'
    disabled?: boolean
    block?: boolean
  }>(),
  {
    type: 'button',
    disabled: false,
    block: false,
  },
)

const classes = computed(() =>
  cn(
    'inline-flex items-center justify-center gap-2 rounded-md px-4 py-2.5 text-sm font-semibold text-inverse transition-opacity duration-150 disabled:cursor-not-allowed disabled:opacity-50',
    props.block && 'w-full',
  ),
)
</script>

<template>
  <button
    :type="type"
    :class="classes"
    :disabled="disabled"
    :style="{
      background: 'linear-gradient(135deg, var(--sv-gradient-cta-start), var(--sv-gradient-cta-end))',
    }"
  >
    <slot />
  </button>
</template>
