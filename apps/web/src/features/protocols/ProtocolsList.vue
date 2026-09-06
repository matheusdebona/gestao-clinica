<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed, onUnmounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ListCard from '@/components/ui/ListCard.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Pagination from '@/components/ui/Pagination.vue'
import SearchField from '@/components/ui/SearchField.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Switch from '@/components/ui/Switch.vue'
import { listProtocols } from '@/features/protocols/api'
import { formatBRL } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import type { Protocol } from '@/types/protocol'

const router = useRouter()
const auth = useAuthStore()

const searchInput = ref('')
const q = ref('')
const page = ref(1)
const activeOnly = ref(true)
let searchTimer: ReturnType<typeof setTimeout> | undefined

watch([q, activeOnly], () => {
  page.value = 1
})

watch(searchInput, (value) => {
  window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(() => {
    q.value = value.trim()
  }, 300)
})

onUnmounted(() => {
  window.clearTimeout(searchTimer)
})

const {
  data: listData,
  isPending,
  isError,
  isFetching,
} = useQuery({
  queryKey: ['protocols', q, page, activeOnly],
  queryFn: () =>
    listProtocols({
      q: q.value || undefined,
      page: page.value,
      is_active: activeOnly.value ? true : undefined,
    }),
  enabled: computed(() => auth.can('protocols.view')),
})

const protocols = computed(() => listData.value?.data ?? [])
const lastPage = computed(() => listData.value?.meta.last_page ?? 1)
const total = computed(() => listData.value?.meta.total ?? 0)

function onSearchEnter(value: string) {
  q.value = value.trim()
}

function openProtocol(id: number) {
  void router.push({ name: 'protocols-show', params: { id: String(id) } })
}

function goNew() {
  void router.push({ name: 'protocols-new' })
}

function protocolMeta(protocol: Protocol) {
  return `Sugerido ${formatBRL(protocol.suggested_price)}`
}

const emptyTitle = computed(() => (q.value ? 'Nenhum protocolo encontrado' : 'Nenhum protocolo ainda'))
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader title="Protocolos" :description="total ? `${total} na clínica` : undefined">
      <template #actions>
        <PermissionGate permission="protocols.create">
          <Button @click="goNew">Novo</Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="!auth.can('protocols.view')" variant="danger" title="Sem permissão">
      Você não pode ver a lista de protocolos.
    </Banner>

    <template v-else>
      <SearchField
        v-model="searchInput"
        placeholder="Nome do protocolo"
        @search="onSearchEnter"
      />

      <Switch v-model="activeOnly" label="Somente ativos" />

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

      <SurfaceCard v-else-if="protocols.length === 0" :padding="false">
        <EmptyState
          :title="emptyTitle"
          :description="q ? 'Tente outro nome.' : 'Monte o primeiro serviço completo da clínica.'"
        >
          <template v-if="!q" #action>
            <PermissionGate permission="protocols.create">
              <Button @click="goNew">Novo protocolo</Button>
            </PermissionGate>
          </template>
        </EmptyState>
      </SurfaceCard>

      <SurfaceCard v-else :padding="false">
        <div class="divide-y divide-border-divider px-5 py-2">
          <ListCard
            v-for="protocol in protocols"
            :key="protocol.id"
            :title="protocol.name"
            :meta="protocolMeta(protocol)"
            :badge="protocol.is_active ? '' : 'Inativo'"
            badge-variant="muted"
            @action="openProtocol(protocol.id)"
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
