<script setup lang="ts">
import { cn } from '@/lib/cn'

withDefaults(
  defineProps<{
    label: string
    hint?: string
    error?: string
    htmlFor?: string
  }>(),
  {
    hint: '',
    error: '',
  },
)
</script>

<template>
  <div :class="cn('flex w-full flex-col gap-1.5')">
    <label
      v-if="label"
      :for="htmlFor"
      class="text-[13px] font-medium text-title/70"
    >
      {{ label }}
    </label>
    <slot :invalid="Boolean(error)" />
    <p v-if="error" class="text-[13px] text-danger" role="alert">
      {{ error }}
    </p>
    <p v-else-if="hint" class="text-[13px] text-muted">
      {{ hint }}
    </p>
  </div>
</template>
