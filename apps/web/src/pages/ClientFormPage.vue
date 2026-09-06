<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import ClientEditor from '@/features/clients/ClientEditor.vue'

const route = useRoute()
const clientId = computed(() => {
  const raw = route.params.id
  if (raw === undefined) {
    return undefined
  }
  const parsed = Number(raw)
  return Number.isInteger(parsed) ? parsed : undefined
})
</script>

<template>
  <ClientEditor :client-id="clientId" />
</template>
