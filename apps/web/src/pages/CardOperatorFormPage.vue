<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import CardOperatorEditor from '@/features/payments/CardOperatorEditor.vue'

const route = useRoute()
const operatorId = computed(() => {
  const raw = route.params.id
  if (raw === undefined) {
    return undefined
  }
  const parsed = Number(raw)
  return Number.isInteger(parsed) ? parsed : undefined
})
</script>

<template>
  <CardOperatorEditor :operator-id="operatorId" />
</template>
