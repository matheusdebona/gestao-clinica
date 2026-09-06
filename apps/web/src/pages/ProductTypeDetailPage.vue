<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import ProductTypeDetail from '@/features/catalog/ProductTypeDetail.vue'

const route = useRoute()
const productTypeId = computed(() => Number(route.params.id))
</script>

<template>
  <ProductTypeDetail :product-type-id="productTypeId" />
</template>
