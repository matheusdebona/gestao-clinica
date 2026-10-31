<script setup lang="ts">
import { keepPreviousData, useQuery, useQueryClient } from '@tanstack/vue-query'
import { MessageCircle, RefreshCw, SlidersHorizontal } from '@lucide/vue'
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ClientSearchBar from '@/components/patterns/ClientSearchBar.vue'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import Badge from '@/components/ui/Badge.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import IconButton from '@/components/ui/IconButton.vue'
import ListCard from '@/components/ui/ListCard.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Pagination from '@/components/ui/Pagination.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import LeadFiltersSheet from '@/features/leads/LeadFiltersSheet.vue'
import LeadStatusDialog from '@/features/leads/LeadStatusDialog.vue'
import { listLeads, logLeadWhatsApp } from '@/features/leads/api'
import { leadStale, staleLabel } from '@/features/leads/stale'
import { useFollowUpTiers } from '@/features/leads/useFollowUpTiers'
import { nextQuickMilestone, quickMilestoneLabel } from '@/features/leads/milestones'
import { useLeadMilestone } from '@/features/leads/useLeadMilestone'
import { useWhatsAppLinks } from '@/features/settings/useWhatsAppLinks'
import { openWhatsApp } from '@/lib/whatsapp'
import {
  activeFilterCount,
  emptyLeadFilters,
  filtersFromQuery,
  filtersToQuery,
  toLeadListParams,
  type LeadFilters,
} from '@/features/leads/filters'
import { useLeadCatalogs } from '@/features/leads/useLeadCatalogs'
import { formatDate } from '@/lib/formatters'
import { formatPhoneBR } from '@/lib/masks'
import { useAuthStore } from '@/stores/auth'
import type { Lead } from '@/types/lead'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { statuses, origins, creators } = useLeadCatalogs()

const filters = computed(() => filtersFromQuery(route.query))
const searchInput = ref(filters.value.q)
const filtersOpen = ref(false)
const statusDialogOpen = ref(false)
const statusLead = ref<Lead | null>(null)

watch(
  () => filters.value.q,
  (q) => {
    if (q !== searchInput.value.trim()) {
      searchInput.value = q
    }
  },
)

function applyFilters(patch: Partial<LeadFilters>) {
  const next: LeadFilters = { ...filters.value, ...patch }
  if (!('page' in patch)) {
    next.page = 1
  }
  void router.replace({ query: filtersToQuery(next) })
}

function clearFilters() {
  void router.replace({
    query: filtersToQuery({ ...emptyLeadFilters(), q: filters.value.q }),
  })
}

function onSearch(value: string) {
  if (value !== filters.value.q) {
    applyFilters({ q: value })
  }
}

const filterCount = computed(() => activeFilterCount(filters.value))
const params = computed(() => toLeadListParams(filters.value))

const {
  data: listData,
  isPending,
  isError,
  isFetching,
} = useQuery({
  queryKey: ['leads', 'list', params],
  queryFn: () => listLeads(params.value),
  enabled: computed(() => auth.can('leads.view')),
  placeholderData: keepPreviousData,
})

const leads = computed(() => listData.value?.data ?? [])
const lastPage = computed(() => listData.value?.meta.last_page ?? 1)
const total = computed(() => listData.value?.meta.total ?? 0)
const hasCriteria = computed(() => Boolean(filters.value.q) || filterCount.value > 0)

const queryClient = useQueryClient()
const { leadUrl } = useWhatsAppLinks()
const { tiers: followUpTiers } = useFollowUpTiers()
const milestone = useLeadMilestone()

function staleBadge(lead: Lead) {
  const stale = leadStale(lead, followUpTiers.value)
  return stale ? { label: staleLabel(stale.days), color: stale.tier.color } : null
}

function callOnWhatsApp(lead: Lead) {
  const url = leadUrl(lead)
  if (!url) {
    return
  }
  openWhatsApp(url)
  void logLeadWhatsApp(lead.id)
    .then(() => queryClient.invalidateQueries({ queryKey: ['leads'] }))
    .catch(() => undefined)
}

function leadMeta(lead: Lead) {
  const bits = [formatPhoneBR(lead.phone) || lead.phone]
  if (lead.client_origin?.name) {
    bits.push(lead.client_origin.name)
  }
  if (lead.created_by?.name) {
    bits.push(`por ${lead.created_by.name}`)
  }
  bits.push(formatDate(lead.created_at))
  if (lead.comments_count) {
    bits.push(lead.comments_count === 1 ? '1 comentário' : `${lead.comments_count} comentários`)
  }
  return bits.join(' · ')
}

function openLead(id: number) {
  void router.push({ name: 'leads-show', params: { id: String(id) } })
}

function goNew() {
  void router.push({ name: 'leads-new' })
}

function openStatus(lead: Lead) {
  statusLead.value = lead
  statusDialogOpen.value = true
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader
      stack-actions
      title="Leads"
      :description="total ? `${total} ${hasCriteria ? 'encontrados' : 'na clínica'}` : undefined"
    >
      <template #actions>
        <PermissionGate permission="leads.manage">
          <Button mobile-block @click="goNew">Novo</Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="!auth.can('leads.view')" variant="danger" title="Sem permissão">
      Você não pode ver a lista de leads.
    </Banner>

    <template v-else>
      <ClientSearchBar v-model="searchInput" placeholder="Nome ou telefone" @search="onSearch" />

      <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
        <Button mobile-block variant="secondary" @click="filtersOpen = true">
          <SlidersHorizontal class="size-4" :stroke-width="1.75" aria-hidden="true" />
          Filtros{{ filterCount ? ` (${filterCount})` : '' }}
        </Button>
        <Button mobile-block v-if="filterCount" variant="ghost" @click="clearFilters"
          >Limpar filtros</Button
        >
      </div>

      <Banner v-if="isError" variant="danger" title="Não foi possível carregar">
        Tente de novo em instantes.
      </Banner>

      <SurfaceCard v-else-if="isPending" :padding="false">
        <div class="flex flex-col gap-3 p-5">
          <Skeleton class="h-12" />
          <Skeleton class="h-12" />
          <Skeleton class="h-12" />
        </div>
      </SurfaceCard>

      <SurfaceCard v-else-if="leads.length === 0" :padding="false">
        <EmptyState
          :title="hasCriteria ? 'Nenhum lead encontrado' : 'Nenhum lead ainda'"
          :description="
            hasCriteria
              ? 'Tente outro nome, telefone ou filtro.'
              : 'Cadastre os contatos que chegam do Instagram, indicações e outras origens.'
          "
        >
          <template #action>
            <Button mobile-block v-if="filterCount" variant="secondary" @click="clearFilters"
              >Limpar filtros</Button
            >
            <PermissionGate v-else-if="!hasCriteria" permission="leads.manage">
              <Button mobile-block @click="goNew">Novo lead</Button>
            </PermissionGate>
          </template>
        </EmptyState>
      </SurfaceCard>

      <SurfaceCard v-else :padding="false">
        <div class="divide-y divide-border-divider px-5 py-2">
          <div v-for="lead in leads" :key="lead.id" class="flex flex-col">
          <div class="flex items-center gap-1">
            <div class="min-w-0 flex-1">
              <ListCard
                :title="lead.name"
                :meta="leadMeta(lead)"
                :badge="lead.status?.name ?? ''"
                :badge-variant="lead.status?.color ?? 'muted'"
                @action="openLead(lead.id)"
              >
                <template v-if="staleBadge(lead)" #status>
                  <Badge :variant="staleBadge(lead)!.color">{{ staleBadge(lead)!.label }}</Badge>
                </template>
              </ListCard>
            </div>
            <PermissionGate permission="leads.manage">
              <IconButton label="Chamar no WhatsApp" @click="callOnWhatsApp(lead)">
                <MessageCircle class="size-4" :stroke-width="1.75" />
              </IconButton>
            </PermissionGate>
            <PermissionGate v-if="!lead.client_id && !lead.cancelled_at" permission="leads.manage">
              <IconButton label="Mudar status" @click="openStatus(lead)">
                <RefreshCw class="size-4" :stroke-width="1.75" />
              </IconButton>
            </PermissionGate>
          </div>
          <PermissionGate v-if="nextQuickMilestone(lead)" permission="leads.manage">
            <div class="flex gap-2 px-2 pb-3 max-sm:flex-col">
              <Button
                mobile-block
                variant="secondary"
                :loading="milestone.isPending.value && milestone.variables.value?.id === lead.id"
                @click="milestone.mutate({ id: lead.id, milestone: nextQuickMilestone(lead)! })"
              >
                {{ quickMilestoneLabel(nextQuickMilestone(lead)!) }}
              </Button>
            </div>
          </PermissionGate>
          </div>
        </div>
      </SurfaceCard>

      <Pagination
        v-if="lastPage > 1"
        :page="filters.page"
        :last-page="lastPage"
        :disabled="isFetching"
        @update:page="applyFilters({ page: $event })"
      />
    </template>

    <LeadFiltersSheet
      v-model:open="filtersOpen"
      :filters="filters"
      :statuses="statuses"
      :origins="origins"
      :creators="creators"
      :total="total"
      @change="applyFilters"
      @clear="clearFilters"
    />

    <LeadStatusDialog v-model:open="statusDialogOpen" :lead="statusLead" :statuses="statuses" />
  </div>
</template>
