<script setup lang="ts">
import { useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import Button from '@/components/ui/Button.vue'

const router = useRouter()
</script>

<template>
  <div class="flex flex-wrap gap-2">
    <PermissionGate permission="client_origins.manage">
      <Button variant="secondary" @click="router.push({ name: 'client-origins' })">Origens</Button>
    </PermissionGate>
    <PermissionGate permission="campaigns.manage">
      <Button variant="secondary" @click="router.push({ name: 'campaigns' })">Campanhas</Button>
    </PermissionGate>
  </div>
</template>
