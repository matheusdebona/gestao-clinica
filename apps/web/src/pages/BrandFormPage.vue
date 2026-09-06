<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import BrandEditor from '@/features/catalog/BrandEditor.vue'

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
  <BrandEditor :brand-id="brandId" />
</template>
