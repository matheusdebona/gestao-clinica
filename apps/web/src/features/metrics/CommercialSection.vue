<script setup lang="ts">
import { computed } from 'vue'
import RankBar from '@/components/patterns/RankBar.vue'
import Banner from '@/components/ui/Banner.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LineChart from '@/components/ui/LineChart.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import MetricCard from '@/components/patterns/MetricCard.vue'
import { revenueChartPoints, maxNumeric, rankRatio } from '@/features/metrics/chart'
import { GRANULARITY_LABELS, budgetStatusLabel, paymentKindLabel } from '@/features/metrics/labels'
import { formatBRL, formatPercent } from '@/lib/formatters'
import type { CommercialMetrics } from '@/types/metrics'

const props = defineProps<{
  data?: CommercialMetrics
  pending: boolean
  error: boolean
}>()

function formatChartValue(value: number): string {
  return formatBRL(value)
}

const chart = computed(() => {
  if (!props.data) {
    return { labels: [] as string[], values: [] as number[] }
  }
  return revenueChartPoints(props.data.series, props.data.granularity)
})

const mixMax = computed(() => maxNumeric((props.data?.payment_mix ?? []).map((row) => row.amount)))

const funnelRows = computed(() =>
  Object.entries(props.data?.budget_funnel.by_status ?? {}).map(([status, count]) => ({
    status,
    count,
    label: budgetStatusLabel(status),
  })),
)

const funnelMax = computed(() => maxNumeric(funnelRows.value.map((row) => row.count)))
</script>

<template>
  <section class="flex flex-col gap-3">
    <h2>Comercial</h2>

    <Banner v-if="error" variant="danger" title="Comercial">
      Não foi possível carregar as métricas comerciais.
    </Banner>

    <template v-else-if="pending">
      <div class="grid grid-cols-2 gap-3">
        <Skeleton class="h-24" />
        <Skeleton class="h-24" />
        <Skeleton class="h-24" />
        <Skeleton class="h-24" />
      </div>
      <Skeleton class="h-44" />
    </template>

    <template v-else-if="data">
      <div class="grid grid-cols-2 gap-3">
        <MetricCard label="Vendas" :hint="`${data.kpis.sales_count} confirmadas`">
          {{ data.kpis.sales_count }}
        </MetricCard>
        <MetricCard label="Desconto médio" hint="lista vs ofertado">
          {{ formatPercent(data.kpis.avg_discount_percent) }}
        </MetricCard>
        <MetricCard label="Orçamentos enviados">
          {{ data.budget_funnel.sent_in_period }}
        </MetricCard>
        <MetricCard label="Taxa de aceite" hint="aceitos no período ÷ enviados">
          {{ formatPercent(data.budget_funnel.acceptance_rate) }}
        </MetricCard>
      </div>

      <SurfaceCard>
        <p class="text-[13px] font-medium text-muted">
          Receita {{ GRANULARITY_LABELS[data.granularity] }}
        </p>
        <div class="mt-3">
          <LineChart
            :labels="chart.labels"
            :values="chart.values"
            label="Receita no período"
            :format-value="formatChartValue"
          />
        </div>
      </SurfaceCard>

      <SurfaceCard>
        <p class="text-[13px] font-medium text-muted">Mix de pagamento</p>
        <EmptyState
          v-if="data.payment_mix.length === 0"
          title="Nenhum pagamento"
          description="Não há pagamentos de vendas confirmadas neste período."
        />
        <div v-else class="mt-2 divide-y divide-border-divider">
          <RankBar
            v-for="row in data.payment_mix"
            :key="row.payment_method_id"
            :label="row.name"
            :meta="paymentKindLabel(row.kind)"
            :value="formatBRL(row.amount)"
            :ratio="rankRatio(row.amount, mixMax)"
          />
        </div>
      </SurfaceCard>

      <SurfaceCard>
        <p class="text-[13px] font-medium text-muted">Funil de orçamento</p>
        <p class="mt-1 text-[13px] text-muted">
          {{ data.budget_funnel.accepted_in_period }} aceitos ·
          {{ data.budget_funnel.rejected_in_period }} recusados
        </p>
        <div class="mt-2 divide-y divide-border-divider">
          <RankBar
            v-for="row in funnelRows"
            :key="row.status"
            :label="row.label"
            :value="String(row.count)"
            :ratio="rankRatio(row.count, funnelMax)"
          />
        </div>
      </SurfaceCard>
    </template>
  </section>
</template>
