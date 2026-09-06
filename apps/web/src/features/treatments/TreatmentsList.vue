<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import ClientSearchBar from '@/components/patterns/ClientSearchBar.vue'
import Banner from '@/components/ui/Banner.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormField from '@/components/ui/FormField.vue'
import ListCard from '@/components/ui/ListCard.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Pagination from '@/components/ui/Pagination.vue'
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { listTreatments } from '@/features/treatments/api'
import { TREATMENT_STATUS_BADGE, TREATMENT_STATUS_LABELS } from '@/features/treatments/labels'
import { formatBRL } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import type { Treatment, TreatmentStatus } from '@/types/treatment'

const router = useRouter()
const auth = useAuthStore()

const searchInput = ref('')
const q = ref('')
const page = ref(1)
const status = ref('')

const statusOptions = [
  { value: '', label: 'Todas as situações' },
  { value: 'open', label: TREATMENT_STATUS_LABELS.open },
  { value: 'completed', label: TREATMENT_STATUS_LABELS.completed },
  { value: 'cancelled', label: TREATMENT_STATUS_LABELS.cancelled },
]

watch([q, status], () => {
  page.value = 1
})

const {
  data: listData,
  isPending,
  isError,
} = useQuery({
  queryKey: ['treatments', q, page, status],
  queryFn: () =>
    listTreatments({
      q: q.value || undefined,
      page: page.value,
      per_page: 20,
      status: (status.value || undefined) as TreatmentStatus | undefined,
    }),
  enabled: computed(() => auth.can('treatments.view')),
})

const treatments = computed(() => listData.value?.data ?? [])
const lastPage = computed(() => listData.value?.meta.last_page ?? 1)
const total = computed(() => listData.value?.meta.total ?? 0)

function onSearch(value: string) {
  q.value = value
}

function openTreatment(id: number) {
  void router.push({ name: 'treatments-show', params: { id: String(id) } })
}

function treatmentMeta(treatment: Treatment) {
  const cost = formatBRL(treatment.total_cost)
  return `Venda #${treatment.sale_id} · Custo ${cost}`
}

const emptyTitle = computed(() => (q.value ? 'Nenhum tratamento encontrado' : 'Nenhum tratamento ainda'))
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader title="Tratamentos" :description="total ? `${total} na clínica` : undefined" />

    <Banner v-if="!auth.can('treatments.view')" variant="danger" title="Sem permissão">
      Você não pode ver a lista de tratamentos.
    </Banner>

    <template v-else>
      <ClientSearchBar
        v-model="searchInput"
        placeholder="Nome ou WhatsApp do cliente"
        @search="onSearch"
      />

      <FormField label="Situação">
        <Select v-model="status" :options="statusOptions" />
      </FormField>

      <Banner v-if="isError" variant="danger" title="Não foi possível carregar">
        Tente de novo em instantes.
      </Banner>

      <SurfaceCard v-else-if="isPending" :padding="false">
        <div class="flex flex-col gap-3 p-5">
          <Skeleton class="h-12" />
          <Skeleton class="h-12" />
          <Skeleton class="h-12" />
        </div>
      </SurfaceCard>

      <SurfaceCard v-else-if="treatments.length === 0" :padding="false">
        <EmptyState
          :title="emptyTitle"
          :description="q || status ? 'Tente outro cliente ou situação.' : 'Abra um tratamento a partir de uma venda confirmada.'"
        />
      </SurfaceCard>

      <SurfaceCard v-else :padding="false">
        <div class="divide-y divide-border-divider px-5 py-2">
          <ListCard
            v-for="treatment in treatments"
            :key="treatment.id"
            :title="treatment.client?.name ?? `Cliente #${treatment.client_id}`"
            :meta="treatmentMeta(treatment)"
            :badge="TREATMENT_STATUS_LABELS[treatment.status]"
            :badge-variant="TREATMENT_STATUS_BADGE[treatment.status]"
            @action="openTreatment(treatment.id)"
          />
        </div>
      </SurfaceCard>

      <Pagination
        v-if="lastPage > 1"
        :page="page"
        :last-page="lastPage"
        @update:page="page = $event"
      />
    </template>
  </div>
</template>
