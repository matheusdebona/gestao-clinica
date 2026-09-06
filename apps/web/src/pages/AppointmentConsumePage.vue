<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppointmentConsume from '@/features/treatments/AppointmentConsume.vue'

const route = useRoute()
const appointmentId = computed(() => {
  const parsed = Number(route.params.id)
  return Number.isInteger(parsed) ? parsed : 0
})
</script>

<template>
  <AppointmentConsume :appointment-id="appointmentId" />
</template>
