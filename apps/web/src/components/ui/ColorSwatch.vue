<script setup lang="ts">
defineProps<{
  name: string
  hex: string
}>()
</script>

<template>
  <div class="flex items-center gap-3">
    <div
      class="size-8 shrink-0 rounded-full border border-border-subtle"
      :style="{ background: hex }"
      :title="hex"
    />
    <div class="min-w-0">
      <p class="text-[13px] font-medium text-title">{{ name }}</p>
      <p class="font-mono text-[12px] text-muted">{{ hex }}</p>
    </div>
  </div>
</template>
