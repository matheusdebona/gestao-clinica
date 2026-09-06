<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import SaleEditor from '@/features/sales/SaleEditor.vue'

const route = useRoute()
const saleId = computed(() => {
  const raw = route.params.id
  if (raw === undefined) {
    return undefined
  }
  const parsed = Number(raw)
  return Number.isInteger(parsed) ? parsed : undefined
})
</script>

<template>
  <SaleEditor :sale-id="saleId" />
</template>
