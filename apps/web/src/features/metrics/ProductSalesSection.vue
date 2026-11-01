<script setup lang="ts">
import { ArrowDown, ArrowUp } from '@lucide/vue'
import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'
import MetricCard from '@/components/patterns/MetricCard.vue'
import MoneyDisplay from '@/components/patterns/MoneyDisplay.vue'
import RankBar from '@/components/patterns/RankBar.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormField from '@/components/ui/FormField.vue'
import InfoTip from '@/components/ui/InfoTip.vue'
import SectionTitle from '@/components/ui/SectionTitle.vue'
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Tabs from '@/components/ui/Tabs.vue'
import { getProductSalesMetrics } from '@/features/metrics/api'
import {
  PRODUCT_SALES_COLUMNS,
  PRODUCT_SALES_SORT_OPTIONS,
  defaultSortDir,
  formatUnits,
  sortProductSales,
  totalUnitsLabel,
  type ProductSalesSortKey,
  type SortDir,
} from '@/features/metrics/productSales'
import { formatBRL, formatPercent } from '@/lib/formatters'

const props = defineProps<{
  from: string
  to: string
  enabled: boolean
}>()

const view = ref<'products' | 'protocols'>('products')
const viewItems = [
  { value: 'products', label: 'Por produto' },
  { value: 'protocols', label: 'Por protocolo' },
]
const typeId = ref('')
const productId = ref('')
const sortKey = ref<ProductSalesSortKey>('revenue')
const sortDir = ref<SortDir>('desc')

const enabledRef = computed(() => props.enabled)
// A visão por protocolo é sempre do período inteiro (sem filtros de produto).
const activeType = computed(() => (view.value === 'products' && typeId.value ? Number(typeId.value) : undefined))
const activeProduct = computed(() => (view.value === 'products' && productId.value ? Number(productId.value) : undefined))
const { data, isPending, isError, isFetching } = useQuery({
  queryKey: ['metrics', 'product-sales', () => props.from, () => props.to, activeType, activeProduct],
  queryFn: () =>
    getProductSalesMetrics({
      from: props.from,
      to: props.to,
      product_type_id: activeType.value,
      product_id: activeProduct.value,
    }),
  enabled: enabledRef,
  placeholderData: keepPreviousData,
})

const filtered = computed(() => Boolean(activeType.value || activeProduct.value))
const totals = computed(() => data.value?.totals)

const typeOptions = computed(() => [
  { value: '', label: 'Todos os tipos' },
  ...(data.value?.options.product_types ?? []).map((t) => ({ value: String(t.id), label: t.name })),
])
const productOptions = computed(() => [
  { value: '', label: 'Todos os produtos' },
  ...(data.value?.options.products ?? [])
    .filter((p) => !typeId.value || String(p.product_type_id) === typeId.value)
    .map((p) => ({ value: String(p.id), label: p.name })),
])

// Produto que não é do tipo escolhido sai do filtro.
watch(typeId, () => {
  if (productId.value && !productOptions.value.some((o) => o.value === productId.value)) {
    productId.value = ''
  }
})

const rows = computed(() => sortProductSales(data.value?.products ?? [], sortKey.value, sortDir.value))
const maxRevenue = computed(() => Math.max(1, ...rows.value.map((r) => Number(r.revenue) || 0)))
const maxProtocolRevenue = computed(() =>
  Math.max(1, ...(data.value?.protocols ?? []).map((r) => Number(r.revenue) || 0)),
)

const mobileSort = computed({
  get: () => sortKey.value as string,
  set: (value: string) => {
    sortKey.value = value as ProductSalesSortKey
    sortDir.value = defaultSortDir(sortKey.value)
  },
})

function sortBy(key: ProductSalesSortKey) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortDir.value = defaultSortDir(key)
  }
}

function clearFilters() {
  typeId.value = ''
  productId.value = ''
}

function salesLabel(count: number): string {
  return `${count} ${count === 1 ? 'venda' : 'vendas'}`
}

const unallocated = computed(() => Number(totals.value?.unallocated_revenue ?? 0))

const hint =
  'Vendas confirmadas no período (data da venda, horário de Brasília). Faturamento de cada produto = o que foi cobrado por ele, já com o desconto do item e o da venda; produtos de protocolos contam como os avulsos. A soma bate com o faturamento das métricas comerciais. Extras cobrados na sessão não entram.'
</script>

<template>
  <section class="flex flex-col gap-3" aria-labelledby="product-sales-title">
    <SectionTitle id="product-sales-title" title="Vendas por produto" :hint="hint" />

    <Banner v-if="isError" variant="danger" title="Vendas por produto">
      Não foi possível carregar.
    </Banner>

    <div v-else-if="isPending" class="grid grid-cols-2 gap-3">
      <Skeleton class="h-24" />
      <Skeleton class="h-24" />
      <Skeleton class="col-span-2 h-64" />
    </div>

    <template v-else-if="data && totals">
      <div class="grid gap-3 sm:grid-cols-2">
        <MetricCard
          :label="filtered ? 'Faturamento filtrado' : 'Faturamento em produtos'"
          :hint="
            filtered
              ? `${formatPercent(totals.share_percent)} do faturamento do período`
              : unallocated > 0
                ? `de ${formatBRL(totals.period_revenue)} no período`
                : 'Igual ao faturamento do período'
          "
        >
          <MoneyDisplay size="lg" :value="totals.revenue" />
        </MetricCard>
        <MetricCard
          label="Vendas"
          :hint="`${totals.products_count} ${totals.products_count === 1 ? 'produto' : 'produtos'}`"
        >
          {{ totals.sales_count }}
        </MetricCard>
      </div>

      <SurfaceCard>
        <div class="flex flex-col gap-3">
          <Tabs v-model="view" :items="viewItems" class="self-start" />

          <div v-if="view === 'products'" class="grid gap-3 sm:grid-cols-2">
            <FormField label="Tipo de produto" html-for="ps-type">
              <Select id="ps-type" v-model="typeId" :options="typeOptions" :filter-threshold="8" />
            </FormField>
            <FormField label="Produto" html-for="ps-product">
              <Select id="ps-product" v-model="productId" :options="productOptions" :filter-threshold="8" />
            </FormField>
            <div class="flex gap-3 max-sm:flex-col sm:col-span-2" :class="!filtered && 'sm:hidden'">
              <FormField label="Ordenar por" html-for="ps-sort" class="sm:hidden">
                <Select id="ps-sort" v-model="mobileSort" :options="PRODUCT_SALES_SORT_OPTIONS" />
              </FormField>
              <Button v-if="filtered" variant="secondary" mobile-block class="sm:ml-auto" @click="clearFilters">
                Limpar filtros
              </Button>
            </div>
          </div>
        </div>

        <!-- Por produto -->
        <template v-if="view === 'products'">
          <EmptyState
            v-if="!rows.length"
            :title="filtered ? 'Nenhum produto com esses filtros' : 'Nenhum produto vendido'"
            :description="filtered ? 'Mude os filtros ou o período.' : 'Não há vendas confirmadas com produtos no período.'"
          />

          <template v-else>
            <!-- Desktop: tabela -->
            <div class="mt-4 overflow-x-auto max-sm:hidden" :class="isFetching && 'opacity-70'">
              <table class="w-full text-left text-[14px]">
                <thead>
                  <tr class="border-b border-border-divider">
                    <th
                      v-for="col in PRODUCT_SALES_COLUMNS"
                      :key="col.key"
                      scope="col"
                      class="py-2 font-medium text-muted"
                      :class="col.numeric ? 'pl-3 text-right' : 'pr-3'"
                      :aria-sort="sortKey === col.key ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'"
                    >
                      <button
                        type="button"
                        class="inline-flex min-h-10 items-center gap-1 text-[13px] hover:text-title"
                        :class="col.numeric && 'flex-row-reverse'"
                        @click="sortBy(col.key)"
                      >
                        {{ col.label }}
                        <ArrowUp v-if="sortKey === col.key && sortDir === 'asc'" class="size-3.5" :stroke-width="1.75" />
                        <ArrowDown v-else-if="sortKey === col.key" class="size-3.5" :stroke-width="1.75" />
                      </button>
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-border-divider">
                  <tr v-for="row in rows" :key="row.product_id" class="align-top">
                    <td class="py-2.5 pr-3">
                      <span class="block font-medium text-title">{{ row.product_name }}</span>
                      <span class="block text-[13px] text-muted">{{ row.product_type_name ?? 'Sem tipo' }}</span>
                    </td>
                    <td class="py-2.5 pl-3 text-right tabular-nums text-body">{{ formatUnits(row.units, row.unit_symbol) }}</td>
                    <td class="py-2.5 pl-3 text-right tabular-nums text-body">{{ row.sales_count }}</td>
                    <td class="py-2.5 pl-3 text-right tabular-nums text-body">{{ formatBRL(row.avg_unit_price) }}</td>
                    <td class="py-2.5 pl-3 text-right tabular-nums font-medium text-title">{{ formatBRL(row.revenue) }}</td>
                    <td class="py-2.5 pl-3 text-right tabular-nums text-body">{{ formatPercent(row.share_percent) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="border-t border-border-divider font-medium text-title">
                    <td class="py-2.5 pr-3">Total</td>
                    <td class="py-2.5 pl-3 text-right tabular-nums">{{ totalUnitsLabel(rows) }}</td>
                    <td class="py-2.5 pl-3 text-right tabular-nums">{{ totals.sales_count }}</td>
                    <td class="py-2.5 pl-3 text-right text-muted">—</td>
                    <td class="py-2.5 pl-3 text-right tabular-nums">{{ formatBRL(totals.revenue) }}</td>
                    <td class="py-2.5 pl-3 text-right tabular-nums">{{ formatPercent(totals.share_percent) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <!-- Mobile: cartões -->
            <ul class="mt-4 flex flex-col divide-y divide-border-divider sm:hidden" :class="isFetching && 'opacity-70'">
              <li v-for="row in rows" :key="row.product_id" class="flex flex-col gap-1.5 py-3 first:pt-0">
                <div class="flex items-baseline justify-between gap-3">
                  <span class="min-w-0 text-[15px] font-medium text-title">{{ row.product_name }}</span>
                  <span class="shrink-0 tabular-nums text-[15px] font-medium text-title">{{ formatBRL(row.revenue) }}</span>
                </div>
                <div class="flex items-baseline justify-between gap-3 text-[13px] text-muted">
                  <span class="min-w-0">
                    {{ formatUnits(row.units, row.unit_symbol) }} · {{ salesLabel(row.sales_count) }} · médio
                    {{ formatBRL(row.avg_unit_price) }}
                  </span>
                  <span class="shrink-0 tabular-nums">{{ formatPercent(row.share_percent) }}</span>
                </div>
                <span v-if="row.product_type_name" class="text-[13px] text-muted">{{ row.product_type_name }}</span>
                <div class="h-1.5 overflow-hidden rounded-full glass-clear" aria-hidden="true">
                  <div class="h-full rounded-full bg-brand" :style="{ width: `${Math.round(((Number(row.revenue) || 0) / maxRevenue) * 1000) / 10}%` }" />
                </div>
              </li>
              <li class="flex items-baseline justify-between gap-3 pt-3 text-[15px] font-semibold text-title">
                <span>Total · {{ salesLabel(totals.sales_count) }}</span>
                <span class="tabular-nums">{{ formatBRL(totals.revenue) }}</span>
              </li>
            </ul>
          </template>

          <p v-if="!filtered && unallocated > 0" class="mt-3 flex items-center gap-1.5 text-[13px] text-muted">
            + {{ formatBRL(totals.unallocated_revenue) }} em vendas sem produto = {{ formatBRL(totals.period_revenue) }}
            <InfoTip
              text="Vendas confirmadas sem itens (ou com itens zerados) entram no faturamento do período, mas não em nenhum produto."
              label="Sobre: vendas sem produto"
            />
          </p>
        </template>

        <!-- Por protocolo -->
        <template v-else>
          <EmptyState
            v-if="!data.protocols.length"
            title="Nenhum produto vendido"
            description="Não há vendas confirmadas com produtos no período."
          />
          <div v-else class="mt-4 flex flex-col">
            <RankBar
              v-for="row in data.protocols"
              :key="row.protocol_id ?? 'avulsos'"
              :label="row.protocol_name ?? 'Produtos avulsos'"
              :value="`${formatBRL(row.revenue)} · ${formatPercent(row.share_percent)}`"
              :meta="`${salesLabel(row.sales_count)} · ${row.products_count} ${row.products_count === 1 ? 'produto' : 'produtos'}`"
              :ratio="(Number(row.revenue) || 0) / maxProtocolRevenue"
            />
          </div>
          <p class="mt-3 text-[13px] text-muted">
            Os produtos entram na venda pelo protocolo aplicado ou avulsos; o faturamento é o mesmo da visão por produto.
          </p>
        </template>
      </SurfaceCard>
    </template>
  </section>
</template>
