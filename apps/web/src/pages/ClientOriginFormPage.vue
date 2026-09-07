<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import ClientOriginEditor from '@/features/clients/ClientOriginEditor.vue'

const route = useRoute()
const originId = computed(() => {
  const raw = route.params.id
  if (raw === undefined) {
    return undefined
  }
  const parsed = Number(raw)
  return Number.isInteger(parsed) ? parsed : undefined
})
</script>

<template>
  <ClientOriginEditor :origin-id="originId" />
</template>
