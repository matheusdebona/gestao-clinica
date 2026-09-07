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
    :class="cn('glass-clear animate-pulse rounded-md', $props.class)"
    aria-hidden="true"
  />
</template>
