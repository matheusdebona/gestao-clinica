<script setup lang="ts">
import { cn } from '@/lib/cn'
import { computed } from 'vue'
import type { Component } from 'vue'

const props = withDefaults(
  defineProps<{
    label: string
    active?: boolean
    disabled?: boolean
    icon?: Component
    nested?: boolean
    compact?: boolean
    tone?: 'inverse' | 'default'
  }>(),
  {
    active: false,
    disabled: false,
    nested: false,
    compact: false,
    tone: 'inverse',
  },
)

const inverse = computed(() => props.tone === 'inverse')

const classes = computed(() =>
  cn(
    'flex w-full items-center gap-3 rounded-[10px] py-2.5 text-left text-[15px] font-medium transition-colors',
    props.compact ? 'relative justify-center px-2' : 'px-3',
    props.nested && !props.compact && 'pl-9',
    props.active && inverse.value && 'bg-brand/40 text-inverse',
    props.active && !inverse.value && 'bg-brand/15 text-brand',
    !props.active &&
      !props.disabled &&
      inverse.value &&
      'text-inverse/70 hover:bg-inverse/10 hover:text-inverse',
    !props.active &&
      !props.disabled &&
      !inverse.value &&
      'text-title hover:bg-surface/35',
    props.disabled && inverse.value && 'cursor-not-allowed text-inverse/35',
    props.disabled && !inverse.value && 'cursor-not-allowed text-muted',
  ),
)
</script>

<template>
  <span
    :class="classes"
    :aria-current="active ? 'page' : undefined"
    :title="compact ? label : undefined"
  >
    <component :is="icon" v-if="icon" class="size-4 shrink-0" :stroke-width="1.75" />
    <span :class="compact ? 'sr-only' : 'min-w-0 truncate'">{{ label }}</span>
    <span v-if="$slots.badge && !compact" class="ml-auto flex shrink-0 items-center gap-1.5">
      <slot name="badge" />
    </span>
    <span v-else-if="$slots.badge && compact" class="absolute right-1 top-1">
      <slot name="badge" />
    </span>
  </span>
</template>
