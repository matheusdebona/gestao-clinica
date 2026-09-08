<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import CampaignEditor from '@/features/clients/CampaignEditor.vue'

const route = useRoute()
const campaignId = computed(() => {
  const raw = route.params.id
  if (raw === undefined) {
    return undefined
  }
  const parsed = Number(raw)
  return Number.isInteger(parsed) ? parsed : undefined
})
</script>

<template>
  <CampaignEditor :campaign-id="campaignId" />
</template>
