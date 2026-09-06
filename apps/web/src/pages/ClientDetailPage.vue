<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import ClientDetail from '@/features/clients/ClientDetail.vue'

const route = useRoute()
const clientId = computed(() => Number(route.params.id))
</script>

<template>
  <ClientDetail :client-id="clientId" />
</template>
