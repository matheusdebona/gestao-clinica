<script setup lang="ts">
import { cn } from '@/lib/cn'
import type { Component } from 'vue'

const props = withDefaults(
  defineProps<{
    material?: 'regular' | 'clear' | 'dark'
    padding?: boolean
    as?: string | Component
    class?: string
  }>(),
  {
    material: 'regular',
    padding: false,
    as: 'div',
  },
)

const materialClass = {
  regular: 'glass-regular',
  clear: 'glass-clear',
  dark: 'glass-dark',
} as const
</script>

<template>
  <component
    :is="as"
    :class="cn(materialClass[material], 'rounded-card', padding && 'p-5', $props.class)"
  >
    <slot />
  </component>
</template>
