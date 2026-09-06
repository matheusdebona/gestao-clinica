<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import ProductDetail from '@/features/products/ProductDetail.vue'

const route = useRoute()
const productId = computed(() => Number(route.params.id))
</script>

<template>
  <ProductDetail :product-id="productId" />
</template>
