<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppointmentDetail from '@/features/appointments/AppointmentDetail.vue'

const route = useRoute()
const appointmentId = computed(() => {
  const parsed = Number(route.params.id)
  return Number.isInteger(parsed) ? parsed : 0
})
</script>

<template>
  <AppointmentDetail :appointment-id="appointmentId" />
</template>
