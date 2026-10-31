<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import LeadDetail from '@/features/leads/LeadDetail.vue'

const route = useRoute()
const leadId = computed(() => Number(route.params.id))
</script>

<template>
  <LeadDetail :lead-id="leadId" />
</template>
