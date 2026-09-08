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
import { listCardBrandsPage } from '@/features/payments/api'
import PaymentCatalogShortcuts from '@/features/payments/PaymentCatalogShortcuts.vue'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()
const page = ref(1)
const activeOnly = ref(true)

watch(activeOnly, () => {
  page.value = 1
})

const { data: listData, isPending, isError, isFetching } = useQuery({
  queryKey: ['card-brands', 'catalog', page, activeOnly],
  queryFn: () =>
    listCardBrandsPage({
      page: page.value,
      active_only: activeOnly.value || undefined,
    }),
  enabled: computed(() => auth.can('card_brands.manage')),
})

const brands = computed(() => listData.value?.data ?? [])
const lastPage = computed(() => listData.value?.meta.last_page ?? 1)
const total = computed(() => listData.value?.meta.total ?? 0)
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader title="Bandeiras" :description="total ? `${total} na clínica` : undefined">
      <template #actions>
        <PermissionGate permission="card_brands.manage">
          <Button @click="router.push({ name: 'card-brands-new' })">Nova</Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="!auth.can('card_brands.manage')" variant="danger" title="Sem permissão">
      Você não pode gerenciar bandeiras.
    </Banner>

    <template v-else>
      <PaymentCatalogShortcuts current="brands" />

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

      <SurfaceCard v-else-if="brands.length === 0" :padding="false">
        <EmptyState
          title="Nenhuma bandeira ainda"
          description="Cadastre Visa, Mastercard e as demais bandeiras usadas na clínica."
        >
          <template #action>
            <Button @click="router.push({ name: 'card-brands-new' })">Nova bandeira</Button>
          </template>
        </EmptyState>
      </SurfaceCard>

      <SurfaceCard v-else :padding="false">
        <div class="divide-y divide-border-divider px-5 py-2">
          <ListCard
            v-for="brand in brands"
            :key="brand.id"
            :title="brand.name"
            :meta="brand.code"
            :badge="brand.is_active ? '' : 'Inativa'"
            badge-variant="muted"
            @action="router.push({ name: 'card-brands-show', params: { id: String(brand.id) } })"
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
