<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import PaymentMethodDetail from '@/features/payments/PaymentMethodDetail.vue'

const route = useRoute()
const methodId = computed(() => Number(route.params.id))
</script>

<template>
  <PaymentMethodDetail :method-id="methodId" />
</template>
