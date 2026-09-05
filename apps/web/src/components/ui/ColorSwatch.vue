<script setup lang="ts">
defineProps<{
  name: string
  hex: string
}>()
</script>

<template>
  <div class="flex flex-col gap-2">
    <div
      class="h-14 w-full rounded-md border border-border-subtle shadow-card"
      :style="{ background: hex }"
      :title="hex"
    />
    <div>
      <p class="text-caption font-semibold text-title">{{ name }}</p>
      <p class="font-mono text-caption text-muted">{{ hex }}</p>
    </div>
  </div>
</template>

<style scoped>
.text-caption {
  font-size: var(--sv-text-caption);
  line-height: var(--sv-leading-caption);
}
</style>
