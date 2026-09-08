<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import CardOperatorDetail from '@/features/payments/CardOperatorDetail.vue'

const route = useRoute()
const operatorId = computed(() => Number(route.params.id))
</script>

<template>
  <CardOperatorDetail :operator-id="operatorId" />
</template>
