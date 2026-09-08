<script setup lang="ts">
import { useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import Button from '@/components/ui/Button.vue'

const props = defineProps<{
  current?: 'methods' | 'brands'
}>()

const router = useRouter()
</script>

<template>
  <div class="flex flex-wrap gap-2">
    <PermissionGate v-if="props.current !== 'methods'" permission="payment_methods.manage">
      <Button variant="secondary" @click="router.push({ name: 'payment-methods' })">
        Métodos
      </Button>
    </PermissionGate>
    <PermissionGate v-if="props.current !== 'brands'" permission="card_brands.manage">
      <Button variant="secondary" @click="router.push({ name: 'card-brands' })">Bandeiras</Button>
    </PermissionGate>
    <PermissionGate v-if="props.current" permission="sales.view">
      <Button variant="ghost" @click="router.push({ name: 'sales' })">Vendas</Button>
    </PermissionGate>
  </div>
</template>
