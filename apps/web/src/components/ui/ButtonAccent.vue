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
    'inline-flex h-10 items-center justify-center gap-2 rounded-[10px] border border-border-subtle bg-surface px-4 text-[15px] font-medium text-title transition-colors duration-150 hover:bg-surface-muted disabled:cursor-not-allowed disabled:opacity-40',
    props.block && 'w-full',
  ),
)
</script>

<template>
  <button :type="type" :class="classes" :disabled="disabled">
    <slot />
  </button>
</template>
