<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import MonthSelect from '@/components/patterns/MonthSelect.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import FinanceShortcuts from '@/features/finance/FinanceShortcuts.vue'
import { currentMonthKey, getDre } from '@/features/finance/api'
import { formatMonthLabel, shiftMonth } from '@/features/finance/labels'
import { formatBRL } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import type { DreBreakdownKey, DreLineKey, DrePeriod } from '@/types/finance'

const auth = useAuthStore()
const router = useRouter()
const canView = computed(() => auth.can('finance.view'))

const month = ref(currentMonthKey())
const { data, isPending, isError } = useQuery({
  queryKey: ['finance', 'dre', month],
  queryFn: () => getDre(month.value),
  enabled: canView,
})

type Row =
  | {
      type: 'line'
      key: DreLineKey
      label: string
      sign: '+' | '−' | '='
      strong?: boolean
      percent?: 'cm' | 'net'
      breakdown?: DreBreakdownKey
      costUp?: boolean
    }
  | { type: 'section'; label: string }

const ROWS: Row[] = [
  { type: 'line', key: 'gross_revenue', label: 'Receita bruta', sign: '+' },
  { type: 'line', key: 'discounts', label: 'Descontos concedidos', sign: '−', costUp: true },
  { type: 'line', key: 'net_revenue', label: 'Receita líquida', sign: '=', strong: true },
  { type: 'section', label: 'Custos variáveis' },
  { type: 'line', key: 'supplies', label: 'Insumos', sign: '−', costUp: true },
  { type: 'line', key: 'card_fees', label: 'Taxas de cartão', sign: '−', costUp: true },
  { type: 'line', key: 'anticipation', label: 'Antecipação', sign: '−', costUp: true },
  { type: 'line', key: 'taxes', label: 'Impostos sobre a receita', sign: '−', costUp: true },
  { type: 'line', key: 'commissions', label: 'Comissões', sign: '−', costUp: true },
  { type: 'line', key: 'losses', label: 'Perdas de estoque', sign: '−', costUp: true },
  {
    type: 'line',
    key: 'other_variable',
    label: 'Outros variáveis',
    sign: '−',
    breakdown: 'other_variable',
    costUp: true,
  },
  {
    type: 'line',
    key: 'contribution_margin',
    label: 'Margem de contribuição',
    sign: '=',
    strong: true,
    percent: 'cm',
  },
  { type: 'section', label: 'Despesas do mês' },
  {
    type: 'line',
    key: 'fixed',
    label: 'Despesas fixas',
    sign: '−',
    breakdown: 'fixed',
    costUp: true,
  },
  {
    type: 'line',
    key: 'personnel',
    label: 'Pessoal e pró-labore',
    sign: '−',
    breakdown: 'personnel',
    costUp: true,
  },
  {
    type: 'line',
    key: 'marketing',
    label: 'Marketing e mídia',
    sign: '−',
    breakdown: 'marketing',
    costUp: true,
  },
  {
    type: 'line',
    key: 'expense_taxes',
    label: 'Impostos e taxas fixas',
    sign: '−',
    breakdown: 'expense_taxes',
    costUp: true,
  },
  {
    type: 'line',
    key: 'financial',
    label: 'Despesas financeiras',
    sign: '−',
    breakdown: 'financial',
    costUp: true,
  },
  {
    type: 'line',
    key: 'depreciation',
    label: 'Depreciação',
    sign: '−',
    breakdown: 'depreciation',
    costUp: true,
  },
  {
    type: 'line',
    key: 'net_result',
    label: 'Resultado líquido',
    sign: '=',
    strong: true,
    percent: 'net',
  },
]

function percentOf(p: DrePeriod, kind: 'cm' | 'net'): string | null {
  return kind === 'cm' ? p.contribution_margin_percent : p.net_margin_percent
}

function formatPercent(v: string | null): string {
  return v === null ? '—' : `${Number(v).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`
}

function variation(key: DreLineKey, costUp?: boolean): { text: string; tone: string } | null {
  if (!data.value) return null
  const cur = Number(data.value.current.lines[key])
  const prev = Number(data.value.previous.lines[key])
  if (cur === prev) return null
  const diff = cur - prev
  const text =
    prev === 0
      ? `${diff > 0 ? '+' : '−'}${formatBRL(Math.abs(diff))}`
      : `${diff > 0 ? '+' : '−'}${Math.abs((diff / Math.abs(prev)) * 100).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`
  const good = costUp ? diff < 0 : diff > 0
  return { text, tone: good ? 'text-success' : 'text-danger' }
}

function amount(p: DrePeriod, key: DreLineKey, sign: string): string {
  const v = Number(p.lines[key])
  if (sign === '−' && v !== 0) return `${v > 0 ? '−' : '+'} ${formatBRL(Math.abs(v))}`
  return formatBRL(v)
}

const visibleRows = computed(() => {
  const d = data.value
  if (!d) return ROWS
  return ROWS.filter(
    (r) =>
      r.type === 'section' ||
      r.strong ||
      Number(d.current.lines[r.key]) !== 0 ||
      Number(d.previous.lines[r.key]) !== 0,
  )
})

const result = computed(() => (data.value ? Number(data.value.current.lines.net_result) : 0))
const isEmpty = computed(
  () =>
    !!data.value && data.value.current.sales_count === 0 && data.value.current.expenses_count === 0,
)
</script>

<template>
  <div class="mx-auto flex w-full max-w-[960px] flex-col gap-6">
    <PageHeader
      title="DRE do mês"
      description="Receitas, custos e despesas pela competência. Só a administração vê."
    >
      <template #actions>
        <Button
          v-if="auth.can('finance.manage')"
          mobile-block
          @click="router.push({ name: 'finance-expenses' })"
        >
          Lançar despesa
        </Button>
      </template>
    </PageHeader>

    <Banner v-if="!canView" variant="danger" title="Sem permissão">
      Só a administração da clínica vê o DRE.
    </Banner>

    <template v-else>
      <FinanceShortcuts current="dre" />

      <SurfaceCard>
        <div class="flex items-end gap-2">
          <Button
            variant="secondary"
            aria-label="Mês anterior"
            @click="month = shiftMonth(month, -1)"
          >
            ‹
          </Button>
          <div class="min-w-0 flex-1">
            <MonthSelect id="dre-month" v-model="month" />
          </div>
          <Button
            variant="secondary"
            aria-label="Próximo mês"
            :disabled="month >= currentMonthKey()"
            @click="month = shiftMonth(month, 1)"
          >
            ›
          </Button>
        </div>
      </SurfaceCard>

      <Banner v-if="isError" variant="danger" title="Não foi possível carregar">
        Tente de novo em instantes.
      </Banner>
      <SurfaceCard v-else-if="isPending || !data">
        <Skeleton class="h-40" />
      </SurfaceCard>
      <template v-else>
        <Banner
          v-if="!data.checks.tax_configured"
          variant="warning"
          title="Impostos não configurados"
        >
          Sem o regime tributário, os impostos sobre a receita ficam zerados. Configure em
          Configurações → Impostos.
        </Banner>
        <Banner v-if="isEmpty" variant="info" title="Mês sem lançamentos">
          Nenhuma venda confirmada nem despesa em {{ formatMonthLabel(data.month) }}.
        </Banner>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <SurfaceCard>
            <p class="text-[13px] text-muted">Receita líquida</p>
            <p class="mt-1 tabular-nums text-[18px] font-semibold text-title">
              {{ formatBRL(data.current.lines.net_revenue) }}
            </p>
          </SurfaceCard>
          <SurfaceCard>
            <p class="text-[13px] text-muted">Margem de contribuição</p>
            <p class="mt-1 tabular-nums text-[18px] font-semibold text-title">
              {{ formatPercent(data.current.contribution_margin_percent) }}
            </p>
          </SurfaceCard>
          <SurfaceCard>
            <p class="text-[13px] text-muted">Resultado</p>
            <p
              class="mt-1 tabular-nums text-[18px] font-semibold"
              :class="result < 0 ? 'text-danger' : 'text-success'"
            >
              {{ formatBRL(result) }}
            </p>
          </SurfaceCard>
          <SurfaceCard>
            <p class="text-[13px] text-muted">Margem líquida</p>
            <p class="mt-1 tabular-nums text-[18px] font-semibold text-title">
              {{ formatPercent(data.current.net_margin_percent) }}
            </p>
          </SurfaceCard>
        </div>

        <SurfaceCard>
          <h2>Ponto de equilíbrio</h2>
          <template v-if="data.current.break_even_revenue !== null">
            <p class="mt-2 tabular-nums text-[22px] font-semibold text-title">
              {{ formatBRL(data.current.break_even_revenue) }}
            </p>
            <p class="mt-1 text-[15px] text-muted">
              Receita líquida necessária para cobrir
              {{ formatBRL(data.current.lines.operating_expenses) }} de despesas com margem de
              {{ formatPercent(data.current.contribution_margin_percent) }}.
              <span
                v-if="data.current.break_even_gap !== null"
                :class="Number(data.current.break_even_gap) < 0 ? 'text-danger' : 'text-success'"
              >
                {{
                  Number(data.current.break_even_gap) < 0
                    ? `Faltam ${formatBRL(-Number(data.current.break_even_gap))}.`
                    : `Acima em ${formatBRL(data.current.break_even_gap)}.`
                }}
              </span>
            </p>
          </template>
          <p v-else class="mt-2 text-[15px] text-muted">
            Precisa de vendas com margem de contribuição positiva no mês para calcular.
          </p>
        </SurfaceCard>

        <SurfaceCard>
          <div class="flex items-baseline justify-between gap-3">
            <h2>Demonstrativo</h2>
            <span class="shrink-0 text-[13px] text-muted">
              vs. {{ formatMonthLabel(data.previous_month) }}
            </span>
          </div>
          <div class="mt-2 divide-y divide-border-divider">
            <template v-for="(row, i) in visibleRows" :key="i">
              <p
                v-if="row.type === 'section'"
                class="pt-4 pb-1 text-[13px] font-semibold uppercase tracking-wide text-muted"
              >
                {{ row.label }}
              </p>
              <div v-else class="py-3">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <p
                      class="text-[15px]"
                      :class="row.strong ? 'font-semibold text-title' : 'text-body'"
                    >
                      {{ row.label }}
                      <span v-if="row.percent" class="font-normal text-muted">
                        · {{ formatPercent(percentOf(data.current, row.percent)) }}
                      </span>
                    </p>
                    <p class="mt-0.5 text-[13px] text-muted tabular-nums">
                      Anterior {{ amount(data.previous, row.key, row.sign) }}
                    </p>
                  </div>
                  <div class="shrink-0 text-right">
                    <p
                      class="tabular-nums text-[15px]"
                      :class="[
                        row.strong ? 'font-semibold' : '',
                        row.key === 'net_result'
                          ? result < 0
                            ? 'text-danger'
                            : 'text-success'
                          : 'text-title',
                      ]"
                    >
                      {{ amount(data.current, row.key, row.sign) }}
                    </p>
                    <p
                      v-if="variation(row.key, row.costUp)"
                      class="mt-0.5 text-[13px] tabular-nums"
                      :class="variation(row.key, row.costUp)?.tone"
                    >
                      {{ variation(row.key, row.costUp)?.text }}
                    </p>
                  </div>
                </div>
                <ul
                  v-if="row.breakdown && data.current.breakdown[row.breakdown].length"
                  class="mt-2 flex flex-col gap-1 border-l-2 border-border-divider pl-3"
                >
                  <li
                    v-for="c in data.current.breakdown[row.breakdown]"
                    :key="c.name"
                    class="flex justify-between gap-3 text-[13px] text-muted"
                  >
                    <span class="min-w-0 truncate">{{ c.name }}</span>
                    <span class="shrink-0 tabular-nums">{{ formatBRL(c.amount) }}</span>
                  </li>
                </ul>
              </div>
            </template>
          </div>
        </SurfaceCard>

        <p class="text-[13px] text-muted">
          Receita e custos variáveis consideram as vendas confirmadas no mês ({{
            data.current.sales_count
          }}). Insumos de tratamentos em aberto são estimados pelo custo previsto ({{
            formatBRL(data.current.supplies_estimated)
          }}). Despesas entram pelo mês de competência.
        </p>
      </template>
    </template>
  </div>
</template>
