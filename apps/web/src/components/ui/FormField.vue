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
      class="text-sm font-semibold text-title"
    >
      {{ label }}
    </label>
    <slot :invalid="Boolean(error)" />
    <p v-if="error" class="text-caption text-danger" role="alert">
      {{ error }}
    </p>
    <p v-else-if="hint" class="text-caption text-muted">
      {{ hint }}
    </p>
  </div>
</template>

<style scoped>
.text-caption {
  font-size: var(--sv-text-caption);
  line-height: var(--sv-leading-caption);
}
</style>
