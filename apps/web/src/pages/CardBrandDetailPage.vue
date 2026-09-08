<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import CardBrandDetail from '@/features/payments/CardBrandDetail.vue'

const route = useRoute()
const brandId = computed(() => Number(route.params.id))
</script>

<template>
  <CardBrandDetail :brand-id="brandId" />
</template>
