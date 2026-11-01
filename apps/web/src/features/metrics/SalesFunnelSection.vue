<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import MetricCard from '@/components/patterns/MetricCard.vue'
import MoneyDisplay from '@/components/patterns/MoneyDisplay.vue'
import RankBar from '@/components/patterns/RankBar.vue'
import Banner from '@/components/ui/Banner.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormField from '@/components/ui/FormField.vue'
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Tabs from '@/components/ui/Tabs.vue'
import { useLeadCatalogs } from '@/features/leads/useLeadCatalogs'
import { getSalesFunnelMetrics } from '@/features/metrics/api'
import { maxNumeric, rankRatio } from '@/features/metrics/chart'
import { formatBRL, formatPercent } from '@/lib/formatters'
import type { SalesFunnelRow } from '@/types/metrics'

const props = defineProps<{
  from: string
  to: string
  enabled: boolean
}>()

const originId = ref('')
const userId = ref('')
const breakdown = ref<'origin' | 'user'>('origin')

const { origins, creators } = useLeadCatalogs()

const originOptions = computed(() => [
  { value: '', label: 'Todas as origens' },
  ...origins.value.map((origin) => ({
    value: String(origin.id),
    label: origin.name,
  })),
])
const userOptions = computed(() => [
  { value: '', label: 'Todos os colaboradores' },
  ...creators.value.map((user) => ({
    value: String(user.id),
    label: user.name,
  })),
])

const fromRef = computed(() => props.from)
const toRef = computed(() => props.to)
const enabledRef = computed(() => props.enabled)

const { data, isPending, isError } = useQuery({
  queryKey: ['metrics', 'sales-funnel', fromRef, toRef, originId, userId],
  queryFn: () =>
    getSalesFunnelMetrics({
      from: props.from,
      to: props.to,
      ...(originId.value ? { client_origin_id: Number(originId.value) } : {}),
      ...(userId.value ? { user_id: Number(userId.value) } : {}),
    }),
  enabled: enabledRef,
})

const BREAKDOWN_ITEMS = [
  { value: 'origin', label: 'Por origem' },
  { value: 'user', label: 'Por colaborador' },
]

const stageMax = computed(() => maxNumeric((data.value?.stages ?? []).map((stage) => stage.count)))
const rows = computed(() =>
  breakdown.value === 'user' ? (data.value?.by_user ?? []) : (data.value?.by_origin ?? []),
)
const rowMax = computed(() => maxNumeric(rows.value.map((row) => row.revenue)))

function plural(count: number, one: string, many: string): string {
  return count === 1 ? `1 ${one}` : `${count} ${many}`
}

function stageMeta(previous: string | null, fromStart: string | null, index: number): string {
  if (index === 0) {
    return 'Base do funil'
  }
  return `${formatPercent(previous)} da etapa anterior · ${formatPercent(fromStart)} dos leads`
}

function rowMeta(row: SalesFunnelRow): string {
  const base =
    row.key === 'no_lead'
      ? plural(row.patients, 'paciente sem lead', 'pacientes sem lead')
      : plural(row.leads, 'lead', 'leads')
  return [
    base,
    plural(row.sold, 'venda', 'vendas'),
    `ticket ${row.ticket_avg ? formatBRL(row.ticket_avg) : '—'}`,
    `conversão ${formatPercent(row.conversion_rate)}`,
  ].join(' · ')
}

function stepsLine(row: SalesFunnelRow): string {
  if (row.key === 'no_lead') {
    return `${row.booked} agendaram → ${row.attended} compareceram → ${row.budgeted} orçamentos`
  }
  return `${row.approached} abordados → ${row.replied} responderam → ${row.booked} agendaram → ${row.attended} compareceram → ${row.budgeted} orçamentos`
}
</script>

<template>
  <section class="flex flex-col gap-3">
    <h2>Funil de vendas</h2>

    <p class="text-[13px] text-muted">
      Leads criados no período, do cadastro até a venda. Cada etapa conta uma vez por lead, mesmo
      que aconteça depois do período. Origem e colaborador são os do lead (quem cadastrou);
      pacientes cadastrados sem lead aparecem em “Sem lead”.
    </p>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
      <FormField label="Origem" html-for="funnel-origin">
        <Select id="funnel-origin" v-model="originId" :options="originOptions" />
      </FormField>
      <FormField label="Colaborador" html-for="funnel-user">
        <Select id="funnel-user" v-model="userId" :options="userOptions" />
      </FormField>
    </div>

    <Banner v-if="isError" variant="danger" title="Funil de vendas">
      Não foi possível carregar o funil de vendas.
    </Banner>

    <template v-else-if="isPending">
      <Skeleton class="h-64" />
      <div class="grid grid-cols-2 gap-3">
        <Skeleton class="h-24" />
        <Skeleton class="h-24" />
      </div>
    </template>

    <template v-else-if="data">
      <SurfaceCard>
        <div class="flex items-baseline justify-between gap-3">
          <p class="text-[13px] font-medium text-muted">Lead → venda</p>
          <p class="text-[22px] font-semibold tabular-nums text-title">
            {{ formatPercent(data.kpis.conversion_rate) }}
          </p>
        </div>
        <EmptyState
          v-if="data.kpis.leads === 0"
          class="mt-2"
          title="Sem leads"
          description="Nenhum lead criado neste período com esses filtros."
        />
        <div v-else class="mt-3 divide-y divide-border-divider">
          <RankBar
            v-for="(stage, index) in data.stages"
            :key="stage.key"
            :label="stage.label"
            :meta="stageMeta(stage.rate_from_previous, stage.rate_from_start, index)"
            :value="String(stage.count)"
            :ratio="rankRatio(stage.count, stageMax)"
          />
        </div>
      </SurfaceCard>

      <div class="grid grid-cols-2 gap-3">
        <MetricCard
          label="Receita dos leads"
          :hint="plural(data.kpis.sales_count, 'venda confirmada', 'vendas confirmadas')"
        >
          <MoneyDisplay size="lg" :value="data.kpis.revenue" />
        </MetricCard>
        <MetricCard label="Ticket médio" hint="receita ÷ vendas">
          <MoneyDisplay size="lg" :value="data.kpis.ticket_avg" />
        </MetricCard>
        <MetricCard
          label="Sem lead"
          :hint="`${plural(data.no_lead.patients, 'paciente', 'pacientes')} · ${plural(data.no_lead.sold, 'venda', 'vendas')}`"
        >
          <MoneyDisplay size="lg" :value="data.no_lead.revenue" />
        </MetricCard>
        <MetricCard
          label="Receita total"
          :hint="plural(data.totals.sales_count, 'venda', 'vendas')"
        >
          <MoneyDisplay size="lg" :value="data.totals.revenue" />
        </MetricCard>
      </div>

      <SurfaceCard>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <p class="text-[13px] font-medium text-muted">Receita, ticket médio e conversão</p>
          <Tabs v-model="breakdown" :items="BREAKDOWN_ITEMS" />
        </div>
        <EmptyState
          v-if="rows.length === 0"
          class="mt-2"
          title="Sem dados"
          description="Nada para mostrar neste período."
        />
        <div v-else class="mt-3 divide-y divide-border-divider">
          <div v-for="row in rows" :key="row.key" class="py-3 first:pt-0 last:pb-0">
            <RankBar
              :label="row.label"
              :meta="rowMeta(row)"
              :value="formatBRL(row.revenue)"
              :ratio="rankRatio(row.revenue, rowMax)"
            />
            <p class="mt-1 text-[13px] text-muted">
              {{ stepsLine(row) }}
            </p>
          </div>
        </div>
      </SurfaceCard>
    </template>
  </section>
</template>
