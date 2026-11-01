<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import MetricCard from '@/components/patterns/MetricCard.vue'
import MoneyDisplay from '@/components/patterns/MoneyDisplay.vue'
import RankBar from '@/components/patterns/RankBar.vue'
import Badge from '@/components/ui/Badge.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { getContributionMargin } from '@/features/finance/api'
import { formatBRL, formatIsoDate, formatPercent } from '@/lib/formatters'
import type { MarginGroupRow } from '@/types/finance'

const props = defineProps<{
  from: string
  to: string
  enabled: boolean
}>()

const router = useRouter()
const view = ref<'by_protocol' | 'by_professional' | 'by_seller' | 'by_origin' | 'sales'>(
  'by_protocol',
)
const viewOptions = [
  { value: 'by_protocol', label: 'Por protocolo' },
  { value: 'by_professional', label: 'Por profissional' },
  { value: 'by_seller', label: 'Por vendedor' },
  { value: 'by_origin', label: 'Por origem' },
  { value: 'sales', label: 'Por venda' },
]

const enabledRef = computed(() => props.enabled)
const { data, isPending, isError } = useQuery({
  queryKey: ['metrics', 'contribution-margin', () => props.from, () => props.to],
  queryFn: () => getContributionMargin({ from: props.from, to: props.to }),
  enabled: enabledRef,
})

const t = computed(() => data.value?.totals)

const lines = computed(() => {
  const totals = t.value
  if (!totals) return []
  return [
    { label: 'Receita (vendas + extras cobrados)', value: totals.revenue, sign: '' },
    {
      label: 'Insumos',
      hint:
        Number(totals.supplies_estimated) > 0
          ? `inclui ${formatBRL(totals.supplies_estimated)} previstos de sessões pendentes`
          : '',
      value: totals.supplies,
      sign: '−',
    },
    { label: 'Taxas de cartão e meios de pagamento', value: totals.card_fees, sign: '−' },
    { label: 'Antecipação', value: totals.anticipation, sign: '−' },
    { label: 'Impostos sobre a receita', value: totals.taxes, sign: '−' },
    { label: 'Comissões', value: totals.commissions, sign: '−' },
    {
      label: 'Margem das vendas',
      value: totals.contribution_margin,
      sign: '=',
      strong: true,
    },
    {
      label: 'Perdas de estoque',
      hint: lossHint.value,
      value: totals.losses,
      sign: '−',
    },
  ]
})

const lossHint = computed(() => {
  const r = t.value?.losses_by_reason
  if (!r || Number(t.value?.losses) === 0) return ''
  const parts = [
    Number(r.expired) > 0 ? `vencimento ${formatBRL(r.expired)}` : '',
    Number(r.damaged) > 0 ? `quebra ${formatBRL(r.damaged)}` : '',
    Number(r.other) > 0 ? `outras ${formatBRL(r.other)}` : '',
  ].filter(Boolean)
  return parts.join(' · ')
})

const groupRows = computed<MarginGroupRow[]>(() => {
  if (!data.value || view.value === 'sales') return []
  return data.value[view.value]
})

const maxMargin = computed(() =>
  Math.max(1, ...groupRows.value.map((row) => Math.abs(Number(row.contribution_margin)))),
)

function rowMeta(row: MarginGroupRow): string {
  const deductions =
    Number(row.supplies) +
    Number(row.card_fees) +
    Number(row.anticipation) +
    Number(row.taxes) +
    Number(row.commissions)
  return `Receita ${formatBRL(row.revenue)} · custos ${formatBRL(deductions)} · ${row.sales_count} ${row.sales_count === 1 ? 'venda' : 'vendas'}`
}

const warnings = computed(() => {
  const checks = data.value?.checks
  if (!checks) return []
  const list: string[] = []
  if (!checks.tax_configured)
    list.push('Impostos não configurados: a margem considera imposto zero.')
  if (checks.card_payments_without_fee > 0)
    list.push(
      `${checks.card_payments_without_fee} pagamento(s) de cartão sem regra de taxa no período.`,
    )
  if (!checks.commission_rules) list.push('Nenhuma regra de comissão ativa.')
  return list
})
</script>

<template>
  <section class="flex flex-col gap-3">
    <h2>Margem de contribuição</h2>
    <p class="text-[13px] text-muted">
      Vendas confirmadas no período menos insumos, taxas, antecipação, impostos e comissões; depois,
      as perdas de estoque. A margem bruta acima continua igual.
    </p>

    <Banner v-if="isError" variant="danger" title="Margem de contribuição">
      Não foi possível carregar.
    </Banner>

    <div v-else-if="isPending" class="grid grid-cols-2 gap-3">
      <Skeleton class="h-24" />
      <Skeleton class="h-24" />
      <Skeleton class="col-span-2 h-64" />
    </div>

    <template v-else-if="data && t">
      <template v-if="warnings.length">
        <Banner variant="warning" title="Números incompletos">
          {{ warnings.join(' ') }}
        </Banner>
        <div class="flex flex-col gap-2 sm:flex-row">
          <Button
            v-if="!data.checks.tax_configured"
            mobile-block
            variant="secondary"
            @click="router.push({ name: 'settings-taxes' })"
          >
            Configurar impostos
          </Button>
          <Button
            v-if="data.checks.card_payments_without_fee > 0"
            mobile-block
            variant="secondary"
            @click="router.push({ name: 'settings-card-fees' })"
          >
            Taxas de cartão
          </Button>
          <Button
            v-if="!data.checks.commission_rules"
            mobile-block
            variant="secondary"
            @click="router.push({ name: 'settings-commissions' })"
          >
            Comissões
          </Button>
        </div>
      </template>

      <div class="grid grid-cols-2 gap-3">
        <MetricCard
          label="Margem de contribuição"
          :hint="formatPercent(t.clinic_contribution_margin_percent)"
        >
          <MoneyDisplay size="lg" :value="t.clinic_contribution_margin" />
        </MetricCard>
        <MetricCard label="Receita" :hint="`${t.sales_count} vendas`">
          <MoneyDisplay size="lg" :value="t.revenue" />
        </MetricCard>
      </div>

      <SurfaceCard>
        <h3>Do faturamento à margem</h3>
        <dl class="mt-3 flex flex-col divide-y divide-border-divider">
          <div
            v-for="line in lines"
            :key="line.label"
            class="flex items-start justify-between gap-3 py-2.5"
          >
            <dt class="min-w-0">
              <span
                class="text-[15px]"
                :class="line.strong ? 'font-semibold text-title' : 'text-body'"
              >
                {{ line.label }}
              </span>
              <span v-if="line.hint" class="block text-[13px] text-muted">{{ line.hint }}</span>
            </dt>
            <dd
              class="shrink-0 tabular-nums text-[15px]"
              :class="line.strong ? 'font-semibold text-title' : 'text-body'"
            >
              {{ line.sign === '−' ? '− ' : '' }}{{ formatBRL(line.value) }}
            </dd>
          </div>
          <div class="flex items-start justify-between gap-3 pt-3">
            <dt class="text-[15px] font-semibold text-title">Margem de contribuição da clínica</dt>
            <dd class="shrink-0 text-right tabular-nums">
              <span class="block text-[17px] font-semibold text-title">
                {{ formatBRL(t.clinic_contribution_margin) }}
              </span>
              <span class="block text-[13px] text-muted">
                {{ formatPercent(t.clinic_contribution_margin_percent) }} da receita
              </span>
            </dd>
          </div>
        </dl>
      </SurfaceCard>

      <SurfaceCard>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <h3>Detalhe</h3>
          <div class="sm:w-56">
            <Select id="cm-view" v-model="view" :options="viewOptions" />
          </div>
        </div>

        <p v-if="data.totals.sales_count === 0" class="mt-4 text-[15px] text-muted">
          Nenhuma venda confirmada no período.
        </p>

        <div v-else-if="view !== 'sales'" class="mt-4 flex flex-col">
          <RankBar
            v-for="row in groupRows"
            :key="row.key"
            :label="row.label"
            :value="`${formatBRL(row.contribution_margin)} · ${formatPercent(row.contribution_margin_percent)}`"
            :meta="rowMeta(row)"
            :ratio="Math.max(0, Number(row.contribution_margin)) / maxMargin"
          />
        </div>

        <div v-else class="mt-2 flex flex-col divide-y divide-border-divider">
          <button
            v-for="sale in data.sales"
            :key="sale.sale_id"
            type="button"
            class="flex flex-col gap-1 py-3 text-left"
            @click="router.push({ name: 'sales-show', params: { id: sale.sale_id } })"
          >
            <div class="flex items-baseline justify-between gap-3">
              <span class="min-w-0 truncate text-[15px] font-medium text-title">
                {{ sale.client_name ?? `Venda #${sale.sale_id}` }}
              </span>
              <span class="shrink-0 tabular-nums text-[15px] text-title">
                {{ formatBRL(sale.contribution_margin) }}
              </span>
            </div>
            <span class="text-[13px] text-muted">
              {{ formatIsoDate(sale.sold_at?.slice(0, 10)) }} · {{ sale.seller_name ?? '—' }} ·
              receita {{ formatBRL(sale.revenue) }} ·
              {{ formatPercent(sale.contribution_margin_percent) }}
            </span>
            <span class="text-[13px] text-muted">
              insumos {{ formatBRL(sale.supplies) }} · taxas
              {{ formatBRL(Number(sale.card_fees) + Number(sale.anticipation)) }} · impostos
              {{ formatBRL(sale.taxes) }} · comissões {{ formatBRL(sale.commissions) }}
            </span>
            <Badge v-if="sale.supplies_estimated" variant="warning" class="self-start">
              Insumo previsto
            </Badge>
          </button>
        </div>
      </SurfaceCard>
    </template>
  </section>
</template>
