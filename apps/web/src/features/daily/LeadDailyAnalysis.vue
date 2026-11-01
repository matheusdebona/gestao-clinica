<script setup lang="ts">
import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { SlidersHorizontal } from '@lucide/vue'
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import MetricCard from '@/components/patterns/MetricCard.vue'
import RankBar from '@/components/patterns/RankBar.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Tabs from '@/components/ui/Tabs.vue'
import DailyCheckinCard from '@/features/daily/DailyCheckinCard.vue'
import DailyCheckinsList from '@/features/daily/DailyCheckinsList.vue'
import DailyFiltersSheet from '@/features/daily/DailyFiltersSheet.vue'
import DailyFollowUpQueue from '@/features/daily/DailyFollowUpQueue.vue'
import DailyTeamTable from '@/features/daily/DailyTeamTable.vue'
import { getDailyAnalysis, type DailyKpiKey } from '@/features/daily/api'
import {
  activeDailyFilterCount,
  clinicToday,
  comparisonHint,
  dailyFiltersFromQuery,
  dailyFiltersToQuery,
  toDailyParams,
  type DailyFilters,
  type DailyTab,
} from '@/features/daily/filters'
import LeadFunnelSection from '@/features/leads/LeadFunnelSection.vue'
import ForgottenBudgetsList from '@/features/budgets/ForgottenBudgetsList.vue'
import MyGoalCard from '@/features/goals/MyGoalCard.vue'
import TeamGoalsCard from '@/features/goals/TeamGoalsCard.vue'
import PatientReturnsList from '@/features/returns/PatientReturnsList.vue'
import { rankRatio } from '@/features/metrics/chart'
import { formatIsoDate } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const team = computed(() => auth.can('metrics.view'))

const filters = computed(() => dailyFiltersFromQuery(route.query))
const params = computed(() => toDailyParams(filters.value))
const filtersOpen = ref(false)

function update(patch: Partial<DailyFilters>) {
  void router.replace({ query: dailyFiltersToQuery({ ...filters.value, ...patch }) })
}

function clear() {
  void router.replace({ query: dailyFiltersToQuery({ ...dailyFiltersFromQuery({}), tab: filters.value.tab }) })
}

const { data, isPending, isError, isFetching } = useQuery({
  queryKey: computed(() => ['leads', 'daily-analysis', params.value]),
  queryFn: () => getDailyAnalysis(params.value),
  placeholderData: keepPreviousData,
})

const tabItems = computed(() =>
  team.value
    ? [
        { value: 'resumo', label: 'Resumo' },
        { value: 'equipe', label: 'Equipe' },
        { value: 'checkins', label: 'Check-ins' },
      ]
    : [],
)
const tab = computed<DailyTab>(() => (team.value ? filters.value.tab : 'resumo'))

const periodLabel = computed(() => {
  const { from, to } = params.value
  if (from === to) {
    return from === clinicToday() ? `Hoje, ${formatIsoDate(from)}` : formatIsoDate(from)
  }
  return `${formatIsoDate(from)} a ${formatIsoDate(to)}`
})

const scopeLabel = computed(() => {
  if (!data.value) {
    return ''
  }
  if (!data.value.team) {
    return 'Seus leads'
  }
  const ids = data.value.filters.user_ids
  if (ids.length === 0) {
    return 'Toda a equipe'
  }
  const names = data.value.users.filter((user) => ids.includes(user.id)).map((user) => user.name)
  return names.join(', ')
})

const kpiCards: { key: DailyKpiKey; label: string }[] = [
  { key: 'registered', label: 'Cadastrados' },
  { key: 'touched', label: 'Leads tocados' },
  { key: 'approached', label: 'Abordados' },
  { key: 'replied', label: 'Responderam' },
  { key: 'booked', label: 'Agendaram' },
  { key: 'converted', label: 'Viraram paciente' },
  { key: 'cancelled', label: 'Cancelados' },
]

const originMax = computed(() => Math.max(0, ...(data.value?.registered_by_origin.map((row) => row.total) ?? [])))
const statusMax = computed(() => Math.max(0, ...(data.value?.status_snapshot.map((row) => row.total) ?? [])))
const singleDay = computed(() => params.value.from === params.value.to)
const showCheckin = computed(
  () => singleDay.value && (!data.value?.team || (data.value.filters.user_ids.length === 1 && data.value.filters.user_ids[0] === data.value.viewer_id) || data.value.filters.user_ids.length === 0),
)
</script>

<template>
  <div class="mx-auto flex w-full max-w-[960px] flex-col gap-6">
    <PageHeader
      stack-actions
      title="Resumo do dia"
      :description="`${periodLabel} · ${scopeLabel || 'carregando…'}`"
    >
      <template #actions>
        <Button mobile-block variant="ghost" @click="router.push({ name: 'leads' })">Leads</Button>
        <Button mobile-block variant="secondary" @click="filtersOpen = true">
          <SlidersHorizontal class="size-4" :stroke-width="1.75" />
          Filtros<template v-if="activeDailyFilterCount(filters)"> ({{ activeDailyFilterCount(filters) }})</template>
        </Button>
      </template>
    </PageHeader>

    <Tabs
      v-if="tabItems.length"
      :model-value="tab"
      block
      :items="tabItems"
      @update:model-value="update({ tab: $event as DailyTab })"
    />

    <Banner v-if="isError" variant="danger" title="Resumo do dia">Não foi possível carregar.</Banner>

    <template v-else-if="isPending">
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <Skeleton v-for="n in 4" :key="n" class="h-24" />
      </div>
      <Skeleton class="h-40" />
    </template>

    <template v-else-if="data && tab === 'resumo'">
      <SurfaceCard>
        <h2>Sugestões</h2>
        <ul class="mt-2 flex flex-col gap-2">
          <li v-for="(item, index) in data.suggestions" :key="index">
            <Banner :variant="item.kind === 'warning' ? 'warning' : item.kind === 'success' ? 'success' : 'info'">
              {{ item.text }}
            </Banner>
          </li>
        </ul>
      </SurfaceCard>

      <div class="grid grid-cols-2 gap-3 sm:grid-cols-4" :class="{ 'opacity-70': isFetching }">
        <MetricCard
          v-for="card in kpiCards"
          :key="card.key"
          :label="card.label"
          :hint="comparisonHint(data.days, data.previous.kpis[card.key], data.average_7d.kpis[card.key])"
        >
          {{ data.kpis[card.key] }}
        </MetricCard>
        <MetricCard label="Pendentes" hint="na fila de acompanhamento agora">
          {{ data.follow_up.count }}
        </MetricCard>
      </div>

      <TeamGoalsCard v-if="data.team" />
      <MyGoalCard v-else />

      <LeadFunnelSection :funnel="data.funnel" :show-users="data.team" />

      <DailyFollowUpQueue :items="data.follow_up.leads" :count="data.follow_up.count" />

      <SurfaceCard v-if="data.follow_up.budgets && data.follow_up.budgets.count > 0">
        <div class="flex items-baseline justify-between gap-3">
          <h2>Orçamentos esquecidos</h2>
          <p class="text-[13px] text-muted">{{ data.follow_up.budgets.count }} para cobrar</p>
        </div>
        <p class="mt-1 text-[13px] text-muted">
          Enviados e sem resposta além de um prazo. Cobrar pelo WhatsApp tira da fila até o próximo
          prazo.
        </p>
        <div class="mt-3">
          <ForgottenBudgetsList :items="data.follow_up.budgets.items" />
        </div>
        <div class="mt-3">
          <Button
            mobile-block
            variant="ghost"
            @click="router.push({ name: 'budgets', query: { esquecidos: '1' } })"
          >
            Ver orçamentos esquecidos
          </Button>
        </div>
      </SurfaceCard>

      <SurfaceCard v-if="data.follow_up.returns && data.follow_up.returns.count > 0">
        <div class="flex items-baseline justify-between gap-3">
          <h2>Retornos de pacientes</h2>
          <p class="text-[13px] text-muted">
            {{ data.follow_up.returns.count }} a vencer ·
            {{ data.follow_up.returns.overdue }} atrasados
          </p>
        </div>
        <p class="mt-1 text-[13px] text-muted">
          Pacientes com retorno sugerido nos próximos 7 dias ou já atrasado.
        </p>
        <div class="mt-3">
          <PatientReturnsList :items="data.follow_up.returns.items" />
        </div>
        <div v-if="data.follow_up.returns.count > data.follow_up.returns.items.length" class="mt-3">
          <Button mobile-block variant="ghost" @click="router.push({ name: 'patient-returns' })">
            Ver todos os retornos
          </Button>
        </div>
      </SurfaceCard>

      <div class="grid gap-6 sm:grid-cols-2">
        <SurfaceCard>
          <p class="text-[13px] font-medium text-muted">Cadastrados por origem</p>
          <p v-if="data.registered_by_origin.length === 0" class="mt-2 text-[15px] text-body">Nenhum lead cadastrado.</p>
          <div v-else class="mt-2 divide-y divide-border-divider">
            <RankBar
              v-for="row in data.registered_by_origin"
              :key="row.id ?? 'none'"
              :label="row.label"
              :value="row.total === 1 ? '1 lead' : `${row.total} leads`"
              :ratio="rankRatio(row.total, originMax)"
            />
          </div>
        </SurfaceCard>
        <SurfaceCard>
          <p class="text-[13px] font-medium text-muted">Onde os leads em aberto pararam (agora)</p>
          <p v-if="data.status_snapshot.length === 0" class="mt-2 text-[15px] text-body">Nenhum lead em aberto.</p>
          <div v-else class="mt-2 divide-y divide-border-divider">
            <RankBar
              v-for="row in data.status_snapshot"
              :key="row.id"
              :label="row.label"
              :value="row.total === 1 ? '1 lead' : `${row.total} leads`"
              :ratio="rankRatio(row.total, statusMax)"
            />
          </div>
        </SurfaceCard>
      </div>

      <DailyCheckinCard v-if="showCheckin" :day="params.to" />
    </template>

    <template v-else-if="data && tab === 'equipe'">
      <DailyTeamTable :rows="data.team_rows ?? []" :single-day="singleDay" :totals="data.kpis" />
      <TeamGoalsCard allow-edit />
      <LeadFunnelSection :funnel="data.funnel" />
    </template>

    <DailyCheckinsList v-else-if="tab === 'checkins'" :params="params" />

    <DailyFiltersSheet
      v-model:open="filtersOpen"
      :filters="filters"
      :team="team"
      :users="data?.users ?? []"
      @change="update"
      @clear="clear"
    />
  </div>
</template>
