<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import ProductTypeEditor from '@/features/catalog/ProductTypeEditor.vue'

const route = useRoute()
const productTypeId = computed(() => {
  const raw = route.params.id
  if (raw === undefined) {
    return undefined
  }
  const parsed = Number(raw)
  return Number.isInteger(parsed) ? parsed : undefined
})
</script>

<template>
  <ProductTypeEditor :product-type-id="productTypeId" />
</template>
