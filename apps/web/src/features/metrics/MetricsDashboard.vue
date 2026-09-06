<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import MetricCard from '@/components/patterns/MetricCard.vue'
import MoneyDisplay from '@/components/patterns/MoneyDisplay.vue'
import Banner from '@/components/ui/Banner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import AcquisitionSection from '@/features/metrics/AcquisitionSection.vue'
import CommercialSection from '@/features/metrics/CommercialSection.vue'
import InventoryOpsSection from '@/features/metrics/InventoryOpsSection.vue'
import MarginSection from '@/features/metrics/MarginSection.vue'
import PeriodControl from '@/features/metrics/PeriodControl.vue'
import {
  getAcquisitionMetrics,
  getCommercialMetrics,
  getInventoryMetrics,
  getMarginMetrics,
  getOperationsMetrics,
} from '@/features/metrics/api'
import { currentMonthRange, isValidPeriod } from '@/features/metrics/period'
import { formatIsoDate, formatPercent } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import type { AcquisitionGroupBy, MarginMode } from '@/types/metrics'

const auth = useAuthStore()
const initial = currentMonthRange()
const from = ref(initial.from)
const to = ref(initial.to)
const groupBy = ref<AcquisitionGroupBy>('origin')
const marginMode = ref<MarginMode>('period')

const periodOk = computed(() => isValidPeriod(from.value, to.value))
const canLoad = computed(() => auth.can('metrics.view') && periodOk.value)
const periodLabel = computed(() => `${formatIsoDate(from.value)} – ${formatIsoDate(to.value)}`)

const {
  data: commercial,
  isPending: commercialPending,
  isError: commercialError,
} = useQuery({
  queryKey: ['metrics', 'commercial', from, to],
  queryFn: () => getCommercialMetrics({ from: from.value, to: to.value }),
  enabled: canLoad,
})

const {
  data: acquisition,
  isPending: acquisitionPending,
  isError: acquisitionError,
} = useQuery({
  queryKey: ['metrics', 'acquisition', from, to, groupBy],
  queryFn: () =>
    getAcquisitionMetrics({ from: from.value, to: to.value, group_by: groupBy.value }),
  enabled: canLoad,
})

const {
  data: margin,
  isPending: marginPending,
  isError: marginError,
} = useQuery({
  queryKey: ['metrics', 'margin', from, to, marginMode],
  queryFn: () => getMarginMetrics({ from: from.value, to: to.value, mode: marginMode.value }),
  enabled: canLoad,
})

const {
  data: inventory,
  isPending: inventoryPending,
  isError: inventoryError,
} = useQuery({
  queryKey: ['metrics', 'inventory', from, to],
  queryFn: () => getInventoryMetrics({ from: from.value, to: to.value }),
  enabled: canLoad,
})

const {
  data: operations,
  isPending: operationsPending,
  isError: operationsError,
} = useQuery({
  queryKey: ['metrics', 'operations', from, to],
  queryFn: () => getOperationsMetrics({ from: from.value, to: to.value }),
  enabled: canLoad,
})

const heroPending = computed(
  () => commercialPending.value || acquisitionPending.value || marginPending.value,
)
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-8">
    <PageHeader title="Métricas" :description="periodLabel" />

    <Banner v-if="!auth.can('metrics.view')" variant="danger" title="Sem permissão">
      Você não pode ver as métricas da clínica.
    </Banner>

    <template v-else>
      <PeriodControl v-model:from="from" v-model:to="to" />

      <Banner v-if="!periodOk" variant="danger" title="Período inválido">
        Ajuste as datas para carregar os indicadores.
      </Banner>

      <template v-else>
        <div class="grid grid-cols-2 gap-3">
          <template v-if="heroPending">
            <Skeleton class="h-24" />
            <Skeleton class="h-24" />
            <Skeleton class="h-24" />
            <Skeleton class="h-24" />
          </template>
          <template v-else>
            <MetricCard label="Faturamento" hint="vendas confirmadas">
              <MoneyDisplay size="lg" :value="commercial?.kpis.revenue" />
            </MetricCard>
            <MetricCard label="Ticket médio">
              <MoneyDisplay size="lg" :value="commercial?.kpis.ticket_avg" />
            </MetricCard>
            <MetricCard label="Conversão" hint="consulta → venda">
              {{ formatPercent(acquisition?.kpis.conversion_rate) }}
            </MetricCard>
            <MetricCard label="Margem" :hint="formatPercent(margin?.kpis.margin_percent)">
              <MoneyDisplay size="lg" :value="margin?.kpis.gross_margin" />
            </MetricCard>
          </template>
        </div>

        <CommercialSection
          :data="commercial"
          :pending="commercialPending"
          :error="commercialError"
        />

        <AcquisitionSection
          v-model:group-by="groupBy"
          :data="acquisition"
          :pending="acquisitionPending"
          :error="acquisitionError"
        />

        <MarginSection
          v-model:mode="marginMode"
          :data="margin"
          :pending="marginPending"
          :error="marginError"
        />

        <InventoryOpsSection
          :inventory="inventory"
          :operations="operations"
          :inventory-pending="inventoryPending"
          :operations-pending="operationsPending"
          :inventory-error="inventoryError"
          :operations-error="operationsError"
        />
      </template>
    </template>
  </div>
</template>
