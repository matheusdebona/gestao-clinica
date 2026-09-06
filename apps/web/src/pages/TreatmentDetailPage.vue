<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import TreatmentDetail from '@/features/treatments/TreatmentDetail.vue'

const route = useRoute()
const treatmentId = computed(() => {
  const parsed = Number(route.params.id)
  return Number.isInteger(parsed) ? parsed : 0
})
</script>

<template>
  <TreatmentDetail :treatment-id="treatmentId" />
</template>
