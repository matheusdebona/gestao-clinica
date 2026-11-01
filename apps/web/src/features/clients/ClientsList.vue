<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'
import { SlidersHorizontal } from '@lucide/vue'
import { useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import ClientSearchBar from '@/components/patterns/ClientSearchBar.vue'
import Badge from '@/components/ui/Badge.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ListCard from '@/components/ui/ListCard.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Pagination from '@/components/ui/Pagination.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Switch from '@/components/ui/Switch.vue'
import AttributionShortcuts from '@/features/clients/AttributionShortcuts.vue'
import PatientFiltersSheet from '@/features/clients/PatientFiltersSheet.vue'
import {
  sanitizeTierFilter,
  tierBadgeVariant,
  tierFilterQuery,
  usePatientTiers,
  type TierFilterToken,
} from '@/features/clients/tiers'
import { listClients } from '@/features/clients/api'
import { formatPhoneBR } from '@/lib/masks'
import { useAuthStore } from '@/stores/auth'
import type { Client } from '@/types/client'

const router = useRouter()
const auth = useAuthStore()

const searchInput = ref('')
const q = ref('')
const page = ref(1)
const activeOnly = ref(true)
const filtersOpen = ref(false)
/** Filtro de faixa: [] = Todos. */
const tierFilter = ref<TierFilterToken[]>([])

const { tiers } = usePatientTiers(computed(() => auth.can('clients.view')))
// Faixa apagada nas configurações sai do filtro; sem faixas, o filtro volta para Todos.
watch(tiers, (list) => {
  const clean = sanitizeTierFilter(tierFilter.value, list)
  if (clean.length !== tierFilter.value.length) tierFilter.value = clean
})
const tierParam = computed(() => tierFilterQuery(tierFilter.value))
const filterCount = computed(() => (tierFilter.value.length ? 1 : 0))

watch(q, () => {
  page.value = 1
})

watch([activeOnly, tierParam], () => {
  page.value = 1
})

const {
  data: listData,
  isPending,
  isError,
  isFetching,
} = useQuery({
  queryKey: ['clients', 'list', q, page, activeOnly, tierParam],
  queryFn: () =>
    listClients({
      q: q.value || undefined,
      page: page.value,
      is_active: activeOnly.value ? true : undefined,
      tiers: tierParam.value,
    }),
  enabled: computed(() => auth.can('clients.view')),
})

const clients = computed(() => listData.value?.data ?? [])
const lastPage = computed(() => listData.value?.meta.last_page ?? 1)
const total = computed(() => listData.value?.meta.total ?? 0)

function onSearch(value: string) {
  q.value = value
}

function openClient(id: number) {
  void router.push({ name: 'clients-show', params: { id: String(id) } })
}

function goNew() {
  void router.push({ name: 'clients-new' })
}

function clientMeta(client: Client) {
  const bits = [formatPhoneBR(client.whatsapp) || client.whatsapp]
  if (client.client_origin?.name) {
    bits.push(client.client_origin.name)
  }
  return bits.join(' · ')
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader title="Pacientes" :description="total ? `${total} na clínica` : undefined">
      <template #actions>
        <PermissionGate permission="clients.create">
          <Button @click="goNew">Novo</Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="!auth.can('clients.view')" variant="danger" title="Sem permissão">
      Você não pode ver a lista de pacientes.
    </Banner>

    <template v-else>
      <AttributionShortcuts />

      <ClientSearchBar v-model="searchInput" @search="onSearch" />

      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <Switch v-model="activeOnly" label="Somente ativos" />
        <Button v-if="tiers.length" mobile-block variant="secondary" @click="filtersOpen = true">
          <SlidersHorizontal class="size-4" :stroke-width="1.75" aria-hidden="true" />
          Filtros{{ filterCount ? ` (${filterCount})` : '' }}
        </Button>
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

      <SurfaceCard v-else-if="clients.length === 0" :padding="false">
        <EmptyState
          :title="q || filterCount ? 'Nenhum paciente encontrado' : 'Nenhum paciente ainda'"
          :description="
            q ? 'Tente outro nome ou WhatsApp.' : filterCount ? 'Nenhum paciente nessa faixa.' : 'Cadastre o primeiro paciente da clínica.'
          "
        >
          <template v-if="!q && !filterCount" #action>
            <PermissionGate permission="clients.create">
              <Button @click="goNew">Novo paciente</Button>
            </PermissionGate>
          </template>
        </EmptyState>
      </SurfaceCard>

      <SurfaceCard v-else :padding="false">
        <div class="divide-y divide-border-divider px-5 py-2">
          <ListCard
            v-for="client in clients"
            :key="client.id"
            :title="client.name"
            :meta="clientMeta(client)"
            :badge="client.tier?.name ?? ''"
            :badge-variant="tierBadgeVariant(client.tier?.color)"
            @action="openClient(client.id)"
          >
            <template v-if="!client.is_active" #status>
              <Badge variant="muted">Inativo</Badge>
            </template>
          </ListCard>
        </div>
      </SurfaceCard>

      <PatientFiltersSheet
        v-model:open="filtersOpen"
        :tiers="tiers"
        :selected="tierFilter"
        :total="total"
        @change="tierFilter = $event"
      />

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
