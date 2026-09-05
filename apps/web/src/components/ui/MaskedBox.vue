<script setup lang="ts">
defineProps<{
  value: string
}>()
</script>

<template>
  <div class="rounded-[10px] bg-input px-3.5 py-2.5 font-mono text-sm tracking-wide text-title">
    {{ value }}
  </div>
</template>
