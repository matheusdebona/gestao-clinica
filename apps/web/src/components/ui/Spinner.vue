<script setup lang="ts">
import BrandMark from '@/components/ui/BrandMark.vue'

withDefaults(
  defineProps<{
    size?: 'sm' | 'md'
    label?: string
  }>(),
  {
    size: 'md',
    label: 'Carregando',
  },
)
</script>

<template>
  <span role="status" class="inline-flex">
    <BrandMark
      motion="draw"
      :size="size"
      tone="brand"
      :decorative="false"
      :label="label"
    />
  </span>
</template>
