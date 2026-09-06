<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import ProductEditor from '@/features/products/ProductEditor.vue'

const route = useRoute()
const productId = computed(() => {
  const raw = route.params.id
  if (raw === undefined) {
    return undefined
  }
  const parsed = Number(raw)
  return Number.isInteger(parsed) ? parsed : undefined
})
</script>

<template>
  <ProductEditor :product-id="productId" />
</template>
