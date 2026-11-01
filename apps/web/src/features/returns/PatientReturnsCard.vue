<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import PatientReturnsList from '@/features/returns/PatientReturnsList.vue'
import { listPatientReturns } from '@/features/returns/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const { data, isPending } = useQuery({
  queryKey: ['patient-returns', 'due'],
  queryFn: () => listPatientReturns('due'),
  enabled: computed(() => auth.can('clients.view') && auth.can('sales.view')),
})

const items = computed(() => (data.value?.data ?? []).slice(0, 3))
</script>

<template>
  <SurfaceCard>
    <div class="flex items-baseline justify-between gap-3">
      <h2>Retornos</h2>
      <p v-if="data" class="text-[13px] text-muted">
        {{ data.meta.counts.due }} a vencer · {{ data.meta.counts.overdue }} atrasados
      </p>
    </div>
    <div v-if="isPending" class="mt-3"><Skeleton class="h-12" /></div>
    <EmptyState
      v-else-if="items.length === 0"
      class="mt-2"
      title="Nenhum retorno para os próximos 7 dias"
      description="Os retornos aparecem aqui quando estão perto de vencer."
    />
    <div v-else class="mt-3">
      <PatientReturnsList :items="items" />
    </div>
    <div class="mt-3">
      <Button mobile-block variant="ghost" @click="router.push({ name: 'patient-returns' })">
        Ver todos os retornos
      </Button>
    </div>
  </SurfaceCard>
</template>
