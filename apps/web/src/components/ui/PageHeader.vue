<script setup lang="ts">
defineProps<{
  title: string
  description?: string
}>()
</script>

<template>
  <header class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
    <div class="min-w-0">
      <h1>{{ title }}</h1>
      <p v-if="description" class="mt-1 max-w-xl text-[15px] text-muted">{{ description }}</p>
    </div>
    <div v-if="$slots.actions" class="flex flex-wrap items-center gap-1">
      <slot name="actions" />
    </div>
  </header>
</template>
