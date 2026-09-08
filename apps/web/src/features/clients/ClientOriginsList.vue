<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ListCard from '@/components/ui/ListCard.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Pagination from '@/components/ui/Pagination.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Switch from '@/components/ui/Switch.vue'
import { listClientOrigins } from '@/features/clients/api'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()
const page = ref(1)
const activeOnly = ref(true)

watch(activeOnly, () => {
  page.value = 1
})

const { data: listData, isPending, isError, isFetching } = useQuery({
  queryKey: ['client-origins', page, activeOnly],
  queryFn: () =>
    listClientOrigins({
      page: page.value,
      active_only: activeOnly.value || undefined,
    }),
  enabled: computed(() => auth.can('client_origins.manage')),
})

const origins = computed(() => listData.value?.data ?? [])
const lastPage = computed(() => listData.value?.meta.last_page ?? 1)
const total = computed(() => listData.value?.meta.total ?? 0)
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader title="Origens" :description="total ? `${total} na clínica` : undefined">
      <template #actions>
        <Button variant="ghost" @click="router.push({ name: 'clients' })">Pacientes</Button>
        <PermissionGate permission="client_origins.manage">
          <Button @click="router.push({ name: 'client-origins-new' })">Nova</Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="!auth.can('client_origins.manage')" variant="danger" title="Sem permissão">
      Você não pode gerenciar origens.
    </Banner>

    <template v-else>
      <Switch v-model="activeOnly" label="Somente ativas" />

      <Banner v-if="isError" variant="danger" title="Não foi possível carregar">
        Tente de novo em instantes.
      </Banner>

      <SurfaceCard v-else-if="isPending" :padding="false">
        <div class="flex flex-col gap-3 p-5">
          <Skeleton class="h-12" />
          <Skeleton class="h-12" />
        </div>
      </SurfaceCard>

      <SurfaceCard v-else-if="origins.length === 0" :padding="false">
        <EmptyState
          title="Nenhuma origem ainda"
          description="Cadastre Instagram, indicação e os demais canais de aquisição."
        >
          <template #action>
            <Button @click="router.push({ name: 'client-origins-new' })">Nova origem</Button>
          </template>
        </EmptyState>
      </SurfaceCard>

      <SurfaceCard v-else :padding="false">
        <div class="divide-y divide-border-divider px-5 py-2">
          <ListCard
            v-for="origin in origins"
            :key="origin.id"
            :title="origin.name"
            :badge="origin.is_active ? '' : 'Inativa'"
            badge-variant="muted"
            @action="router.push({ name: 'client-origins-show', params: { id: String(origin.id) } })"
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
