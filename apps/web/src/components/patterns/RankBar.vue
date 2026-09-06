<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    label: string
    value: string
    ratio: number
    meta?: string
  }>(),
  {
    meta: '',
  },
)

const width = computed(() => {
  const clamped = Math.min(1, Math.max(0, props.ratio))
  return `${Math.round(clamped * 1000) / 10}%`
})
</script>

<template>
  <div class="flex flex-col gap-1.5 py-3 first:pt-0 last:pb-0">
    <div class="flex items-baseline justify-between gap-3">
      <p class="min-w-0 truncate text-[15px] font-medium text-title">{{ label }}</p>
      <p class="shrink-0 tabular-nums text-[13px] text-muted">{{ value }}</p>
    </div>
    <p v-if="meta" class="truncate text-[13px] text-muted">{{ meta }}</p>
    <div class="h-1.5 overflow-hidden rounded-full glass-clear" aria-hidden="true">
      <div class="h-full rounded-full bg-brand" :style="{ width }" />
    </div>
  </div>
</template>
