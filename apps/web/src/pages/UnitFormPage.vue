<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import UnitEditor from '@/features/catalog/UnitEditor.vue'

const route = useRoute()
const unitId = computed(() => {
  const raw = route.params.id
  if (raw === undefined) {
    return undefined
  }
  const parsed = Number(raw)
  return Number.isInteger(parsed) ? parsed : undefined
})
</script>

<template>
  <UnitEditor :unit-id="unitId" />
</template>
