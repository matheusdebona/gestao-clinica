<script setup lang="ts">
import { useRouter } from 'vue-router'
import Button from '@/components/ui/Button.vue'

const props = defineProps<{
  current: 'dre' | 'expenses' | 'recurring' | 'equipment' | 'categories'
}>()

const router = useRouter()

const links = [
  { key: 'dre', name: 'finance-dre', label: 'DRE' },
  { key: 'expenses', name: 'finance-expenses', label: 'Despesas' },
  { key: 'recurring', name: 'finance-recurring', label: 'Recorrentes' },
  { key: 'equipment', name: 'finance-equipment', label: 'Equipamentos' },
  { key: 'categories', name: 'finance-categories', label: 'Categorias' },
  { key: 'commissions', name: 'settings-commissions', label: 'Comissões' },
] as const
</script>

<template>
  <div class="flex flex-wrap gap-2">
    <Button
      v-for="link in links.filter((l) => l.key !== props.current)"
      :key="link.key"
      variant="secondary"
      @click="router.push({ name: link.name })"
    >
      {{ link.label }}
    </Button>
  </div>
</template>
