<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import LeadEditor from '@/features/leads/LeadEditor.vue'

const route = useRoute()
const leadId = computed(() => {
  const raw = route.params.id
  if (raw === undefined) {
    return undefined
  }
  const parsed = Number(raw)
  return Number.isInteger(parsed) ? parsed : undefined
})
</script>

<template>
  <LeadEditor :lead-id="leadId" />
</template>
