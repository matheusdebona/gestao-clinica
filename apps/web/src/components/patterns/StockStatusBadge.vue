<script setup lang="ts">
import { computed } from 'vue'
import Badge from '@/components/ui/Badge.vue'

export type StockLevel = 'ok' | 'low' | 'reorder' | 'negative'

const props = withDefaults(
  defineProps<{
    level?: StockLevel
    isLowStock?: boolean
    stockQuantity?: string | number | null
  }>(),
  {
    isLowStock: false,
    stockQuantity: null,
  },
)

const resolved = computed((): StockLevel => {
  if (props.level) {
    return props.level
  }
  const qty = Number(props.stockQuantity)
  if (Number.isFinite(qty) && qty < 0) {
    return 'negative'
  }
  if (props.isLowStock) {
    return 'low'
  }
  return 'ok'
})

const label = computed(() => {
  if (resolved.value === 'low') {
    return 'Estoque baixo'
  }
  if (resolved.value === 'reorder') {
    return 'Reposição'
  }
  if (resolved.value === 'negative') {
    return 'Negativo'
  }
  return 'Normal'
})

const variant = computed(() => {
  if (resolved.value === 'low') {
    return 'warning' as const
  }
  if (resolved.value === 'reorder') {
    return 'purple' as const
  }
  if (resolved.value === 'negative') {
    return 'danger' as const
  }
  return 'success' as const
})
</script>

<template>
  <Badge :variant="variant">{{ label }}</Badge>
</template>
