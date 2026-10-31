<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import MetricCard from '@/components/patterns/MetricCard.vue'
import RankBar from '@/components/patterns/RankBar.vue'
import Banner from '@/components/ui/Banner.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ListCard from '@/components/ui/ListCard.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { maxNumeric, rankRatio } from '@/features/metrics/chart'
import { formatPercent } from '@/lib/formatters'
import type { LeadMetrics } from '@/types/metrics'

const props = defineProps<{
  data?: LeadMetrics
  pending: boolean
  error: boolean
}>()

const router = useRouter()

function openStaleLeads() {
  void router.push({ name: 'leads', query: { parados: '1' } })
}

const staleMeta = computed(() => {
  const days = props.data?.kpis.stale_days ?? 3
  return `Sem contato há ${days === 1 ? '1 dia' : `${days} dias`} ou mais (situação de hoje)`
})

function leadsLabel(count: number): string {
  return count === 1 ? '1 lead' : `${count} leads`
}

function convertedLabel(count: number): string {
  return count === 1 ? '1 virou paciente' : `${count} viraram paciente`
}

function rowMeta(row: { converted: number; conversion_rate: string | null; cancelled: number }, leading: string): string {
  const bits = [leading, formatPercent(row.conversion_rate)]
  if (row.cancelled > 0) {
    bits.push(row.cancelled === 1 ? '1 cancelado' : `${row.cancelled} cancelados`)
  }
  return bits.join(' · ')
}

function share(count: number, total: number): string | null {
  return total > 0 ? String((count / total) * 100) : null
}

const avgDays = computed(() => {
  const value = props.data?.kpis.avg_days_to_conversion
  if (value === null || value === undefined) {
    return '—'
  }
  const days = Number(value)
  const formatted = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 }).format(days)
  return days === 1 ? `${formatted} dia` : `${formatted} dias`
})

const total = computed(() => props.data?.kpis.total ?? 0)
const originMax = computed(() => maxNumeric((props.data?.by_origin ?? []).map((row) => row.total)))
const statusMax = computed(() => maxNumeric((props.data?.by_status ?? []).map((row) => row.total)))
const creatorMax = computed(() =>
  maxNumeric((props.data?.by_creator ?? []).map((row) => row.converted)),
)
</script>

<template>
  <section class="flex flex-col gap-3">
    <h2>Leads</h2>

    <p class="text-[13px] text-muted">
      Leads cadastrados no período, cancelados incluídos. Conta como convertido quem virou
      paciente, mesmo que depois do período.
    </p>

    <Banner v-if="error" variant="danger" title="Leads">
      Não foi possível carregar os leads.
    </Banner>

    <template v-else-if="pending">
      <div class="grid grid-cols-2 gap-3">
        <Skeleton class="h-24" />
        <Skeleton class="h-24" />
        <Skeleton class="h-24" />
        <Skeleton class="h-24" />
      </div>
      <Skeleton class="h-32" />
    </template>

    <template v-else-if="data">
      <div class="grid grid-cols-2 gap-3">
        <MetricCard
          label="Leads"
          :hint="`${data.kpis.cancelled} ${data.kpis.cancelled === 1 ? 'cancelado' : 'cancelados'} (${formatPercent(data.kpis.cancelled_rate)})`"
        >
          {{ data.kpis.total }}
        </MetricCard>
        <MetricCard label="Viraram paciente">
          {{ data.kpis.converted }}
        </MetricCard>
        <MetricCard label="Taxa de conversão" hint="viraram paciente ÷ todos os leads">
          {{ formatPercent(data.kpis.conversion_rate) }}
        </MetricCard>
        <MetricCard label="Tempo até virar paciente" hint="média">
          {{ avgDays }}
        </MetricCard>
      </div>

      <SurfaceCard :padding="false">
        <div class="px-5 py-2">
          <ListCard
            :title="`Leads parados: ${data.kpis.stale}`"
            :meta="staleMeta"
            :badge="data.kpis.stale > 0 ? 'Ver lista' : ''"
            badge-variant="warning"
            @action="openStaleLeads"
          />
        </div>
      </SurfaceCard>

      <EmptyState
        v-if="total === 0"
        title="Sem leads"
        description="Nenhum lead cadastrado neste período."
      />

      <template v-else>
        <SurfaceCard>
          <p class="text-[13px] font-medium text-muted">Por origem</p>
          <div class="mt-2 divide-y divide-border-divider">
            <RankBar
              v-for="row in data.by_origin"
              :key="row.key"
              :label="row.label"
              :meta="rowMeta(row, convertedLabel(row.converted))"
              :value="leadsLabel(row.total)"
              :ratio="rankRatio(row.total, originMax)"
            />
          </div>
        </SurfaceCard>

        <SurfaceCard>
          <p class="text-[13px] font-medium text-muted">Por status</p>
          <div class="mt-2 divide-y divide-border-divider">
            <RankBar
              v-for="row in data.by_status"
              :key="row.key"
              :label="row.label"
              :meta="formatPercent(share(row.total, total))"
              :value="leadsLabel(row.total)"
              :ratio="rankRatio(row.total, statusMax)"
            />
          </div>
        </SurfaceCard>

        <SurfaceCard>
          <p class="text-[13px] font-medium text-muted">Ranking por quem cadastrou</p>
          <div class="mt-2 divide-y divide-border-divider">
            <RankBar
              v-for="row in data.by_creator"
              :key="row.key"
              :label="row.label"
              :meta="rowMeta(row, leadsLabel(row.total))"
              :value="convertedLabel(row.converted)"
              :ratio="rankRatio(row.converted, creatorMax)"
            />
          </div>
        </SurfaceCard>
      </template>
    </template>
  </section>
</template>
