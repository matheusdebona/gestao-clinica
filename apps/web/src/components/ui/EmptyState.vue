<script setup lang="ts">
defineProps<{
  title: string
  description?: string
}>()
</script>

<template>
  <div class="flex flex-col items-center px-6 py-10 text-center">
    <p class="text-[17px] font-semibold tracking-[-0.02em] text-title">{{ title }}</p>
    <p v-if="description" class="mt-1 max-w-sm text-[15px] text-muted">
      {{ description }}
    </p>
    <!-- Full width below `sm` (primary action); unchanged from `sm` up. -->
    <div v-if="$slots.action" class="mt-5 flex w-full flex-col sm:block sm:w-auto">
      <slot name="action" />
    </div>
  </div>
</template>
