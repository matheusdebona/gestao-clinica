<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import Banner from '@/components/ui/Banner.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Tabs from '@/components/ui/Tabs.vue'
import PatientReturnsList from '@/features/returns/PatientReturnsList.vue'
import { listPatientReturns, type PatientReturnFilter } from '@/features/returns/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const canView = computed(() => auth.can('clients.view') && auth.can('sales.view'))
const filter = ref<string>('due')

const FILTERS = [
  { value: 'due', label: 'A vencer' },
  { value: 'overdue', label: 'Atrasados' },
  { value: 'upcoming', label: 'Pendentes' },
  { value: 'done', label: 'Feitos' },
]

const { data, isPending, isError } = useQuery({
  queryKey: ['patient-returns', filter],
  queryFn: () => listPatientReturns(filter.value as PatientReturnFilter),
  enabled: canView,
})

const description = computed(() => {
  const counts = data.value?.meta.counts
  if (!counts) {
    return 'Pacientes com retorno sugerido pelo protocolo'
  }
  return `${counts.due} a vencer em 7 dias ou atrasados · ${counts.overdue} atrasados`
})
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader
      hint="O retorno nasce da venda confirmada de um protocolo com “Retorno sugerido” (ou da conclusão do tratamento) e fica feito sozinho quando o paciente agenda uma nova sessão ou compra de novo."
      :back="{ label: 'Pacientes', to: { name: 'clients' } }" stack-actions title="Retornos" :description="description">
    </PageHeader>

    <Banner v-if="!canView" variant="danger" title="Sem permissão">
      Você não pode ver os retornos dos pacientes.
    </Banner>

    <template v-else>
      <Tabs v-model="filter" block :items="FILTERS" />

      <Banner v-if="isError" variant="danger" title="Não foi possível carregar">
        Tente de novo em instantes.
      </Banner>
      <SurfaceCard v-else-if="isPending">
        <Skeleton class="h-12" />
        <Skeleton class="mt-3 h-12" />
      </SurfaceCard>
      <SurfaceCard v-else-if="data && data.data.length === 0" :padding="false">
        <EmptyState
          title="Nenhum retorno aqui"
          description="Defina o retorno sugerido nos protocolos para acompanhar os pacientes."
        />
      </SurfaceCard>
      <SurfaceCard v-else-if="data">
        <PatientReturnsList :items="data.data" />
      </SurfaceCard>
    </template>
  </div>
</template>
