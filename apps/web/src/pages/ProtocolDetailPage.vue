<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import ProtocolDetail from '@/features/protocols/ProtocolDetail.vue'

const route = useRoute()
const protocolId = computed(() => Number(route.params.id))
</script>

<template>
  <ProtocolDetail :protocol-id="protocolId" />
</template>
