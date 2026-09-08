<script setup lang="ts">
defineProps<{
  value: string
}>()
</script>

<template>
  <div class="glass-field sv-field-control flex h-11 items-center rounded-[12px] px-3.5 font-mono tracking-[0.08em] text-title">
    {{ value }}
  </div>
</template>
