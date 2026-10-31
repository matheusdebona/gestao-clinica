<script setup lang="ts">
import { cn } from '@/lib/cn'

const props = withDefaults(
  defineProps<{
    title: string
    description?: string
    sticky?: boolean
    /** Actions stacked full width below `sm` (last action on top). Pair with Button `mobile-block`. */
    stackActions?: boolean
  }>(),
  {
    sticky: false,
    stackActions: false,
  },
)
</script>

<template>
  <header
    :class="
      cn(
        'flex flex-col gap-3 md:flex-row md:items-end md:justify-between',
        sticky && 'glass-clear sticky top-3 z-20 -mx-2 rounded-[18px] px-4 py-3 md:top-4',
      )
    "
  >
    <div class="min-w-0">
      <h1>{{ title }}</h1>
      <p v-if="description" class="mt-1 max-w-xl text-[15px] text-muted">
        {{ description }}
      </p>
    </div>
    <div
      v-if="$slots.actions"
      :class="
        props.stackActions
          ? 'flex flex-col-reverse gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-1'
          : 'flex flex-wrap items-center gap-1'
      "
    >
      <slot name="actions" />
    </div>
  </header>
</template>
