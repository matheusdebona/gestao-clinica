<script setup lang="ts">
import { cn } from '@/lib/cn'

withDefaults(
  defineProps<{
    padding?: boolean
    class?: string
  }>(),
  {
    padding: true,
  },
)
</script>

<template>
  <section
    :class="cn('rounded-card bg-surface shadow-card', padding && 'p-6', $props.class)"
  >
    <slot />
  </section>
</template>
