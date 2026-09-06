<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import ProtocolEditor from '@/features/protocols/ProtocolEditor.vue'

const route = useRoute()
const protocolId = computed(() => {
  const raw = route.params.id
  if (raw === undefined) {
    return undefined
  }
  const parsed = Number(raw)
  return Number.isInteger(parsed) ? parsed : undefined
})
</script>

<template>
  <ProtocolEditor :protocol-id="protocolId" />
</template>
