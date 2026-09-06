<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import ClientSearchBar from '@/components/patterns/ClientSearchBar.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormField from '@/components/ui/FormField.vue'
import ListCard from '@/components/ui/ListCard.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Pagination from '@/components/ui/Pagination.vue'
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { listSales } from '@/features/sales/api'
import { SALE_STATUS_BADGE, SALE_STATUS_LABELS } from '@/features/sales/labels'
import { formatBRL, formatDateTime } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import type { Sale, SaleStatus } from '@/types/sale'

const router = useRouter()
const auth = useAuthStore()

const searchInput = ref('')
const q = ref('')
const page = ref(1)
const status = ref('')

const statusOptions = [
  { value: '', label: 'Todas as situações' },
  { value: 'draft', label: SALE_STATUS_LABELS.draft },
  { value: 'confirmed', label: SALE_STATUS_LABELS.confirmed },
  { value: 'cancelled', label: SALE_STATUS_LABELS.cancelled },
]

watch([q, status], () => {
  page.value = 1
})

const {
  data: listData,
  isPending,
  isError,
  isFetching,
} = useQuery({
  queryKey: ['sales', q, page, status],
  queryFn: () =>
    listSales({
      q: q.value || undefined,
      page: page.value,
      status: (status.value || undefined) as SaleStatus | undefined,
    }),
  enabled: computed(() => auth.can('sales.view')),
})

const sales = computed(() => listData.value?.data ?? [])
const lastPage = computed(() => listData.value?.meta.last_page ?? 1)
const total = computed(() => listData.value?.meta.total ?? 0)

function onSearch(value: string) {
  q.value = value
}

function openSale(id: number) {
  void router.push({ name: 'sales-show', params: { id: String(id) } })
}

function goNew() {
  void router.push({ name: 'sales-new' })
}

function saleMeta(sale: Sale) {
  const amount = formatBRL(sale.effective_amount)
  const when = formatDateTime(sale.sold_at ?? sale.created_at)
  return `${amount} · ${when}`
}

const emptyTitle = computed(() => (q.value ? 'Nenhuma venda encontrada' : 'Nenhuma venda ainda'))
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader title="Vendas" :description="total ? `${total} na clínica` : undefined">
      <template #actions>
        <PermissionGate permission="sales.create">
          <Button @click="goNew">Nova</Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="!auth.can('sales.view')" variant="danger" title="Sem permissão">
      Você não pode ver a lista de vendas.
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

      <SurfaceCard v-else-if="sales.length === 0" :padding="false">
        <EmptyState
          :title="emptyTitle"
          :description="q || status ? 'Tente outro cliente ou situação.' : 'Monte a primeira venda da clínica.'"
        >
          <template v-if="!q && !status" #action>
            <PermissionGate permission="sales.create">
              <Button @click="goNew">Nova venda</Button>
            </PermissionGate>
          </template>
        </EmptyState>
      </SurfaceCard>

      <SurfaceCard v-else :padding="false">
        <div class="divide-y divide-border-divider px-5 py-2">
          <ListCard
            v-for="sale in sales"
            :key="sale.id"
            :title="sale.client?.name ?? `Cliente #${sale.client_id}`"
            :meta="saleMeta(sale)"
            :badge="SALE_STATUS_LABELS[sale.status]"
            :badge-variant="SALE_STATUS_BADGE[sale.status]"
            @action="openSale(sale.id)"
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
