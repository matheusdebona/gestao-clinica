<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import ListCard from '@/components/ui/ListCard.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { getStaleLeadsSummary, type StaleLeadSummaryItem } from '@/features/leads/api'
import { daysSince, staleLabel } from '@/features/leads/stale'
import { formatPhoneBR } from '@/lib/masks'

const router = useRouter()

const { data, isPending, isError } = useQuery({
  queryKey: ['leads', 'stale-summary'],
  queryFn: getStaleLeadsSummary,
  staleTime: 60_000,
})

const description = computed(() => {
  const days = data.value?.days
  if (days === null || days === undefined) {
    return 'Nenhum prazo de acompanhamento configurado.'
  }
  return `Em aberto e sem contato há ${days === 1 ? '1 dia' : `${days} dias`} ou mais.`
})

const tiers = computed(() => [...(data.value?.tiers ?? [])].reverse())

function openTier(days: number) {
  void router.push({ name: 'leads', query: { parados: String(days) } })
}

function itemMeta(item: StaleLeadSummaryItem): string {
  return [staleLabel(daysSince(item.last_activity_at)), formatPhoneBR(item.phone) || 'Sem telefone'].join(' · ')
}

function openLead(id: number) {
  void router.push({ name: 'leads-show', params: { id: String(id) } })
}

function openAll() {
  void router.push({ name: 'leads', query: { parados: 'todos' } })
}
</script>

<template>
  <SurfaceCard v-if="!isError">
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <p class="text-[13px] font-medium text-muted">Leads parados</p>
        <p class="mt-1 text-[13px] text-muted">{{ description }}</p>
      </div>
      <Skeleton v-if="isPending" class="h-8 w-10" />
      <p v-else class="text-[28px] font-semibold leading-none text-title">{{ data?.count ?? 0 }}</p>
    </div>

    <template v-if="data">
      <p v-if="data.count === 0" class="mt-3 text-[15px] text-body">
        Nenhum lead parado. Tudo em dia.
      </p>
      <template v-else>
        <div class="mt-2 divide-y divide-border-divider">
          <ListCard
            v-for="tier in tiers"
            :key="tier.days"
            :title="tier.label"
            :meta="tier.count === 1 ? '1 lead' : `${tier.count} leads`"
            :badge="String(tier.count)"
            :badge-variant="tier.count > 0 ? tier.color : 'muted'"
            @action="openTier(tier.days)"
          />
        </div>
        <p class="mt-4 text-[13px] font-medium text-muted">Há mais tempo sem contato</p>
        <div class="mt-1 divide-y divide-border-divider">
          <ListCard
            v-for="item in data.leads"
            :key="item.id"
            :title="item.name"
            :meta="itemMeta(item)"
            :badge="item.status?.name ?? ''"
            :badge-variant="item.status?.color ?? 'muted'"
            @action="openLead(item.id)"
          />
        </div>
        <div class="mt-3 flex sm:justify-end">
          <Button mobile-block variant="secondary" @click="openAll">
            Ver {{ data.count === 1 ? 'o lead parado' : `os ${data.count} leads parados` }}
          </Button>
        </div>
      </template>
    </template>
  </SurfaceCard>
</template>
