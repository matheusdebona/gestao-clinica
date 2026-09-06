<script setup lang="ts">
import { useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import Button from '@/components/ui/Button.vue'

const router = useRouter()
</script>

<template>
  <div class="flex flex-wrap gap-2">
    <PermissionGate permission="brands.manage">
      <Button variant="secondary" @click="router.push({ name: 'brands' })">Marcas</Button>
    </PermissionGate>
    <PermissionGate permission="product_types.manage">
      <Button variant="secondary" @click="router.push({ name: 'product-types' })">Tipos</Button>
    </PermissionGate>
    <PermissionGate permission="units.manage">
      <Button variant="secondary" @click="router.push({ name: 'units' })">Unidades</Button>
    </PermissionGate>
    <PermissionGate permission="protocols.view">
      <Button variant="secondary" @click="router.push({ name: 'protocols' })">Ver protocolos</Button>
    </PermissionGate>
    <PermissionGate permission="protocols.create">
      <Button variant="secondary" @click="router.push({ name: 'protocols-new' })">
        Novo protocolo
      </Button>
    </PermissionGate>
  </div>
</template>
