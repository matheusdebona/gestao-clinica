<script setup lang="ts">
defineProps<{
  value: string
}>()
</script>

<template>
  <div class="glass-field flex h-11 items-center rounded-[12px] px-3.5 font-mono text-[15px] tracking-[0.08em] text-title">
    {{ value }}
  </div>
</template>
