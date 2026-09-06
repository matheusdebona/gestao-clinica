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
  }>(),
  {
    active: false,
    disabled: false,
  },
)

const classes = computed(() =>
  cn(
    'flex w-full items-center gap-3 rounded-[10px] px-3 py-2.5 text-left text-[15px] font-medium transition-colors',
    props.active && 'bg-white/12 text-inverse',
    !props.active && !props.disabled && 'text-white/70 hover:bg-white/8 hover:text-inverse',
    props.disabled && 'cursor-not-allowed text-white/35',
  ),
)
</script>

<template>
  <span :class="classes" :aria-current="active ? 'page' : undefined">
    <component :is="icon" v-if="icon" class="size-4 shrink-0" :stroke-width="1.75" />
    <span class="min-w-0 truncate">{{ label }}</span>
    <span v-if="$slots.badge" class="ml-auto shrink-0">
      <slot name="badge" />
    </span>
  </span>
</template>
