<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import SaleDetail from '@/features/sales/SaleDetail.vue'

const route = useRoute()
const saleId = computed(() => Number(route.params.id))
</script>

<template>
  <SaleDetail :sale-id="saleId" />
</template>
