<script setup lang="ts">
import { cn } from '@/lib/cn'

withDefaults(
  defineProps<{
    title: string
    description?: string
    sticky?: boolean
  }>(),
  {
    sticky: false,
  },
)
</script>

<template>
  <header
    :class="
      cn(
        'flex flex-col gap-3 md:flex-row md:items-end md:justify-between',
        sticky &&
          'glass-clear sticky top-3 z-20 -mx-2 rounded-[18px] px-4 py-3 md:top-4',
      )
    "
  >
    <div class="min-w-0">
      <h1>{{ title }}</h1>
      <p v-if="description" class="mt-1 max-w-xl text-[15px] text-muted">{{ description }}</p>
    </div>
    <div v-if="$slots.actions" class="flex flex-wrap items-center gap-1">
      <slot name="actions" />
    </div>
  </header>
</template>
