<script setup lang="ts">
withDefaults(
  defineProps<{
    name: string
    hex?: string
    swatchClass?: string
  }>(),
  {
    hex: '',
    swatchClass: '',
  },
)
</script>

<template>
  <div class="flex items-center gap-3">
    <div
      class="size-8 shrink-0 rounded-full border border-border-subtle"
      :class="swatchClass"
      :style="hex ? { background: hex } : undefined"
      :title="hex || name"
    />
    <div class="min-w-0">
      <p class="text-[13px] font-medium text-title">{{ name }}</p>
      <p class="font-mono text-[12px] text-muted">{{ hex || 'material' }}</p>
    </div>
  </div>
</template>
