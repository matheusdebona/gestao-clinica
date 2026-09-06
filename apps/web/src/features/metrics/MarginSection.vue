<script setup lang="ts">
import MoneyDisplay from '@/components/patterns/MoneyDisplay.vue'
import Banner from '@/components/ui/Banner.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Tabs from '@/components/ui/Tabs.vue'
import MetricCard from '@/components/patterns/MetricCard.vue'
import { MARGIN_MODE_ITEMS } from '@/features/metrics/labels'
import { formatPercent } from '@/lib/formatters'
import type { MarginMetrics, MarginMode } from '@/types/metrics'

const mode = defineModel<MarginMode>('mode', { required: true })

defineProps<{
  data?: MarginMetrics
  pending: boolean
  error: boolean
}>()
</script>

<template>
  <section class="flex flex-col gap-3">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <h2>Margem</h2>
      <Tabs v-model="mode" :items="MARGIN_MODE_ITEMS" />
    </div>

    <p class="text-[13px] text-muted">
      <template v-if="mode === 'cohort_sale'">
        Receita das vendas do período versus o custo já aplicado nesses tratamentos. Pode
        subestimar se ainda há saldo a aplicar.
      </template>
      <template v-else>
        Receita das vendas do período versus o custo das sessões concluídas no mesmo intervalo
        (visão caixa).
      </template>
    </p>

    <Banner v-if="error" variant="danger" title="Margem">
      Não foi possível carregar a margem.
    </Banner>

    <div v-else-if="pending" class="grid grid-cols-2 gap-3">
      <Skeleton class="h-24" />
      <Skeleton class="h-24" />
      <Skeleton class="h-24" />
      <Skeleton class="h-24" />
    </div>

    <template v-else-if="data">
      <div class="grid grid-cols-2 gap-3">
        <MetricCard label="Receita clínica" hint="vendas + extras cobrados">
          <MoneyDisplay size="lg" :value="data.kpis.revenue" />
        </MetricCard>
        <MetricCard label="Custo clínico">
          <MoneyDisplay size="lg" :value="data.kpis.clinical_cost" />
        </MetricCard>
        <MetricCard label="Margem bruta" :hint="formatPercent(data.kpis.margin_percent)">
          <MoneyDisplay size="lg" :value="data.kpis.gross_margin" />
        </MetricCard>
        <MetricCard label="Custo de cortesia">
          <MoneyDisplay size="lg" :value="data.kpis.courtesy_cost" />
        </MetricCard>
      </div>

      <Banner
        v-if="mode === 'cohort_sale' && (data.kpis.pending_fulfillment_count ?? 0) > 0"
        variant="warning"
        title="Saldo a aplicar"
      >
        {{ data.kpis.pending_fulfillment_count }}
        {{ data.kpis.pending_fulfillment_count === 1 ? 'tratamento aberto' : 'tratamentos abertos' }}
        neste recorte. A margem de venda pode subir quando as sessões forem concluídas.
      </Banner>
    </template>
  </section>
</template>
