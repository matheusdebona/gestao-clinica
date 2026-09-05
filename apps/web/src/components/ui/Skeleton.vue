<script setup lang="ts">
import { cn } from '@/lib/cn'

withDefaults(
  defineProps<{
    class?: string
  }>(),
  {},
)
</script>

<template>
  <div
    :class="cn('animate-pulse rounded-md bg-surface-muted', $props.class)"
    aria-hidden="true"
  />
</template>
