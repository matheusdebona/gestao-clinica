<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import CardBrandEditor from '@/features/payments/CardBrandEditor.vue'

const route = useRoute()
const brandId = computed(() => {
  const raw = route.params.id
  if (raw === undefined) {
    return undefined
  }
  const parsed = Number(raw)
  return Number.isInteger(parsed) ? parsed : undefined
})
</script>

<template>
  <CardBrandEditor :brand-id="brandId" />
</template>
