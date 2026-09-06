<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Banner from '@/components/ui/Banner.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormField from '@/components/ui/FormField.vue'
import ListCard from '@/components/ui/ListCard.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Pagination from '@/components/ui/Pagination.vue'
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Switch from '@/components/ui/Switch.vue'
import { listBudgets } from '@/features/budgets/api'
import { BUDGET_STATUS_BADGE, BUDGET_STATUS_LABELS } from '@/features/sales/labels'
import { formatBRL, formatDateTime } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import type { Budget, BudgetStatus } from '@/types/budget'

const router = useRouter()
const auth = useAuthStore()

const page = ref(1)
const status = ref('')
const includeSuperseded = ref(false)

const statusOptions = [
  { value: '', label: 'Todos os status' },
  { value: 'draft', label: BUDGET_STATUS_LABELS.draft },
  { value: 'sent', label: BUDGET_STATUS_LABELS.sent },
  { value: 'accepted', label: BUDGET_STATUS_LABELS.accepted },
  { value: 'rejected', label: BUDGET_STATUS_LABELS.rejected },
  { value: 'expired', label: BUDGET_STATUS_LABELS.expired },
  { value: 'superseded', label: BUDGET_STATUS_LABELS.superseded },
]

watch([status, includeSuperseded], () => {
  page.value = 1
})

const {
  data: listData,
  isPending,
  isError,
  isFetching,
} = useQuery({
  queryKey: ['budgets', page, status, includeSuperseded],
  queryFn: () =>
    listBudgets({
      page: page.value,
      status: (status.value || undefined) as BudgetStatus | undefined,
      include_superseded: includeSuperseded.value,
    }),
  enabled: computed(() => auth.can('budgets.view')),
})

const budgets = computed(() => listData.value?.data ?? [])
const lastPage = computed(() => listData.value?.meta.last_page ?? 1)
const total = computed(() => listData.value?.meta.total ?? 0)

function openSale(saleId: number) {
  void router.push({ name: 'sales-show', params: { id: String(saleId) } })
}

function budgetMeta(budget: Budget) {
  return `v${budget.version} · ${formatBRL(budget.effective_amount)} · ${formatDateTime(budget.created_at)}`
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader
      title="Orçamentos"
      :description="total ? `${total} na caixa de entrada` : 'Abre a venda correspondente.'"
    />

    <Banner v-if="!auth.can('budgets.view')" variant="danger" title="Sem permissão">
      Você não pode ver os orçamentos.
    </Banner>

    <template v-else>
      <FormField label="Status">
        <Select v-model="status" :options="statusOptions" />
      </FormField>

      <Switch
        v-if="!status"
        v-model="includeSuperseded"
        label="Incluir substituídos"
      />

      <Banner v-if="isError" variant="danger" title="Não foi possível carregar">
        Tente de novo em instantes.
      </Banner>

      <SurfaceCard v-else-if="isPending" :padding="false">
        <div class="flex flex-col gap-3 p-5">
          <Skeleton class="h-12" />
          <Skeleton class="h-12" />
        </div>
      </SurfaceCard>

      <SurfaceCard v-else-if="budgets.length === 0" :padding="false">
        <EmptyState
          title="Nenhum orçamento"
          description="Gere um orçamento a partir de uma venda em rascunho."
        />
      </SurfaceCard>

      <SurfaceCard v-else :padding="false">
        <div class="divide-y divide-border-divider px-5 py-2">
          <ListCard
            v-for="budget in budgets"
            :key="budget.id"
            :title="budget.client?.name ?? `Cliente #${budget.client_id}`"
            :meta="budgetMeta(budget)"
            :badge="BUDGET_STATUS_LABELS[budget.status]"
            :badge-variant="BUDGET_STATUS_BADGE[budget.status]"
            @action="openSale(budget.sale_id)"
          />
        </div>
      </SurfaceCard>

      <Pagination
        v-if="lastPage > 1"
        :page="page"
        :last-page="lastPage"
        :disabled="isFetching"
        @update:page="page = $event"
      />
    </template>
  </div>
</template>
