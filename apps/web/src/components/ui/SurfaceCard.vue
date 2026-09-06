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
    :class="cn('rounded-card border border-border-subtle bg-surface', padding && 'p-5', $props.class)"
  >
    <slot />
  </section>
</template>
