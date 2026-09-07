<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import ClientOriginDetail from '@/features/clients/ClientOriginDetail.vue'

const route = useRoute()
const originId = computed(() => Number(route.params.id))
</script>

<template>
  <ClientOriginDetail :origin-id="originId" />
</template>
