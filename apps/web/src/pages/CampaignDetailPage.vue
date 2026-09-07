<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import CampaignDetail from '@/features/clients/CampaignDetail.vue'

const route = useRoute()
const campaignId = computed(() => Number(route.params.id))
</script>

<template>
  <CampaignDetail :campaign-id="campaignId" />
</template>
