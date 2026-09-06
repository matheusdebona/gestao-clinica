<script setup lang="ts">
import { computed } from 'vue'
import MoneyDisplay from '@/components/patterns/MoneyDisplay.vue'
import RankBar from '@/components/patterns/RankBar.vue'
import Banner from '@/components/ui/Banner.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Tabs from '@/components/ui/Tabs.vue'
import MetricCard from '@/components/patterns/MetricCard.vue'
import { maxNumeric, rankRatio } from '@/features/metrics/chart'
import { GROUP_BY_ITEMS } from '@/features/metrics/labels'
import { formatBRL, formatPercent } from '@/lib/formatters'
import type { AcquisitionGroupBy, AcquisitionMetrics } from '@/types/metrics'

const groupBy = defineModel<AcquisitionGroupBy>('groupBy', { required: true })

const props = defineProps<{
  data?: AcquisitionMetrics
  pending: boolean
  error: boolean
}>()

const ranked = computed(() => {
  const rows = [...(props.data?.breakdown ?? [])]
  rows.sort((a, b) => Number(b.sales_revenue) - Number(a.sales_revenue) || b.new_clients - a.new_clients)
  return rows
})

const rankMax = computed(() => maxNumeric(ranked.value.map((row) => row.sales_revenue)))
</script>

<template>
  <section class="flex flex-col gap-3">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <h2>Aquisição</h2>
      <Tabs v-model="groupBy" :items="GROUP_BY_ITEMS" />
    </div>

    <p class="text-[13px] text-muted">
      Conversão lifetime: cliente cadastrado no período com pelo menos uma venda confirmada depois
      do cadastro.
    </p>

    <Banner v-if="error" variant="danger" title="Aquisição">
      Não foi possível carregar a aquisição.
    </Banner>

    <template v-else-if="pending">
      <div class="grid grid-cols-2 gap-3">
        <Skeleton class="h-24" />
        <Skeleton class="h-24" />
      </div>
      <Skeleton class="h-32" />
    </template>

    <template v-else-if="data">
      <div class="grid grid-cols-2 gap-3">
        <MetricCard label="Novos clientes" :hint="`${data.kpis.converted_clients} converteram`">
          {{ data.kpis.new_clients }}
        </MetricCard>
        <MetricCard label="Receita de consulta">
          <MoneyDisplay size="lg" :value="data.kpis.consultation_revenue" />
        </MetricCard>
      </div>

      <SurfaceCard>
        <p class="text-[13px] font-medium text-muted">
          Ranking por {{ groupBy === 'campaign' ? 'campanha' : 'origem' }}
        </p>
        <EmptyState
          v-if="ranked.length === 0"
          title="Sem cadastros"
          description="Nenhum cliente novo neste período."
        />
        <div v-else class="mt-2 divide-y divide-border-divider">
          <RankBar
            v-for="row in ranked"
            :key="row.key"
            :label="row.label"
            :meta="[
              row.origin_label,
              `${row.new_clients} clientes`,
              formatPercent(row.conversion_rate),
            ]
              .filter(Boolean)
              .join(' · ')"
            :value="formatBRL(row.sales_revenue)"
            :ratio="rankRatio(row.sales_revenue, rankMax)"
          />
        </div>
      </SurfaceCard>
    </template>
  </section>
</template>
