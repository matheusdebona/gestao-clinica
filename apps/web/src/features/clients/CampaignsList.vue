<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ListCard from '@/components/ui/ListCard.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Pagination from '@/components/ui/Pagination.vue'
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Switch from '@/components/ui/Switch.vue'
import { listCampaigns, listClientOrigins } from '@/features/clients/api'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const page = ref(1)
const activeOnly = ref(true)

function originFromQuery(): string {
  const raw = route.query.client_origin_id
  return typeof raw === 'string' && raw ? raw : 'all'
}

const originFilter = ref(originFromQuery())

watch(originFilter, () => {
  page.value = 1
})

watch(activeOnly, () => {
  page.value = 1
})

watch(
  () => route.query.client_origin_id,
  () => {
    const next = originFromQuery()
    if (next !== originFilter.value) {
      originFilter.value = next
    }
  },
)

const originsQuery = useQuery({
  queryKey: ['client-origins', 'filter'],
  queryFn: () => listClientOrigins({ page: 1 }),
  enabled: computed(
    () =>
      auth.can('client_origins.manage') ||
      auth.can('clients.create') ||
      auth.can('clients.update'),
  ),
})

const originOptions = computed(() => [
  { value: 'all', label: 'Todas as origens' },
  ...(originsQuery.data.value?.data ?? []).map((origin) => ({
    value: String(origin.id),
    label: origin.name,
  })),
])

const { data: listData, isPending, isError, isFetching } = useQuery({
  queryKey: ['campaigns', page, activeOnly, originFilter],
  queryFn: () =>
    listCampaigns({
      page: page.value,
      active_only: activeOnly.value || undefined,
      client_origin_id: originFilter.value !== 'all' ? Number(originFilter.value) : undefined,
    }),
  enabled: computed(() => auth.can('campaigns.manage')),
})

const campaigns = computed(() => listData.value?.data ?? [])
const lastPage = computed(() => listData.value?.meta.last_page ?? 1)
const total = computed(() => listData.value?.meta.total ?? 0)

function onOriginFilterUpdate(value: string) {
  originFilter.value = value
  const query = { ...route.query }
  if (value === 'all') {
    delete query.client_origin_id
  } else {
    query.client_origin_id = value
  }
  void router.replace({ query })
}

function goNew() {
  void router.push({
    name: 'campaigns-new',
    query: originFilter.value !== 'all' ? { client_origin_id: originFilter.value } : {},
  })
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader title="Campanhas" :description="total ? `${total} na clínica` : undefined">
      <template #actions>
        <Button variant="ghost" @click="router.push({ name: 'clients' })">Clientes</Button>
        <PermissionGate permission="campaigns.manage">
          <Button @click="goNew">Nova</Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="!auth.can('campaigns.manage')" variant="danger" title="Sem permissão">
      Você não pode gerenciar campanhas.
    </Banner>

    <template v-else>
      <Switch v-model="activeOnly" label="Somente ativas" />
      <Select
        :model-value="originFilter"
        :options="originOptions"
        placeholder="Origem"
        @update:model-value="onOriginFilterUpdate"
      />

      <Banner v-if="isError" variant="danger" title="Não foi possível carregar">
        Tente de novo em instantes.
      </Banner>

      <SurfaceCard v-else-if="isPending" :padding="false">
        <div class="flex flex-col gap-3 p-5">
          <Skeleton class="h-12" />
          <Skeleton class="h-12" />
        </div>
      </SurfaceCard>

      <SurfaceCard v-else-if="campaigns.length === 0" :padding="false">
        <EmptyState
          title="Nenhuma campanha ainda"
          description="Cada campanha pertence a uma origem."
        >
          <template #action>
            <Button @click="goNew">Nova campanha</Button>
          </template>
        </EmptyState>
      </SurfaceCard>

      <SurfaceCard v-else :padding="false">
        <div class="divide-y divide-border-divider px-5 py-2">
          <ListCard
            v-for="campaign in campaigns"
            :key="campaign.id"
            :title="campaign.name"
            :meta="campaign.client_origin?.name"
            :badge="campaign.is_active ? '' : 'Inativa'"
            badge-variant="muted"
            @action="router.push({ name: 'campaigns-show', params: { id: String(campaign.id) } })"
          />
        </div>
      </SurfaceCard>

      <Pagination
        v-if="lastPage > 1"
        :page="page"
        :last-page="lastPage"
        :disabled="isFetching"
        @update:page="page = $event"
      />
    </template>
  </div>
</template>
