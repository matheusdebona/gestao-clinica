<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import BrandDetail from '@/features/catalog/BrandDetail.vue'

const route = useRoute()
const brandId = computed(() => Number(route.params.id))
</script>

<template>
  <BrandDetail :brand-id="brandId" />
</template>
