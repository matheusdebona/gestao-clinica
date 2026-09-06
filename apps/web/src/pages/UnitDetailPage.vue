<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import UnitDetail from '@/features/catalog/UnitDetail.vue'

const route = useRoute()
const unitId = computed(() => Number(route.params.id))
</script>

<template>
  <UnitDetail :unit-id="unitId" />
</template>
