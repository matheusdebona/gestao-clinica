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
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Switch from '@/components/ui/Switch.vue'
import { listBrands, listProductTypes } from '@/features/catalog/api'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()
const page = ref(1)
const activeOnly = ref(true)
const brandFilter = ref('all')

watch([activeOnly, brandFilter], () => {
  page.value = 1
})

const brandsQuery = useQuery({
  queryKey: ['brands', 'filter'],
  queryFn: () => listBrands({ page: 1 }),
  enabled: computed(() => auth.can('product_types.manage') || auth.can('brands.manage')),
})

const brandOptions = computed(() => [
  { value: 'all', label: 'Todas as marcas' },
  ...(brandsQuery.data.value?.data ?? []).map((brand) => ({
    value: String(brand.id),
    label: brand.name,
  })),
])

const { data: listData, isPending, isError, isFetching } = useQuery({
  queryKey: ['product-types', page, activeOnly, brandFilter],
  queryFn: () =>
    listProductTypes({
      page: page.value,
      active_only: activeOnly.value || undefined,
      brand_id: brandFilter.value !== 'all' ? Number(brandFilter.value) : undefined,
    }),
  enabled: computed(() => auth.can('product_types.manage')),
})

const types = computed(() => listData.value?.data ?? [])
const lastPage = computed(() => listData.value?.meta.last_page ?? 1)
const total = computed(() => listData.value?.meta.total ?? 0)
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader title="Tipos de produto" :description="total ? `${total} na clínica` : undefined">
      <template #actions>
        <Button variant="ghost" @click="router.push({ name: 'products' })">Produtos</Button>
        <PermissionGate permission="product_types.manage">
          <Button @click="router.push({ name: 'product-types-new' })">Novo</Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="!auth.can('product_types.manage')" variant="danger" title="Sem permissão">
      Você não pode gerenciar tipos.
    </Banner>

    <template v-else>
      <Switch v-model="activeOnly" label="Somente ativos" />
      <Select v-model="brandFilter" :options="brandOptions" placeholder="Marca" />

      <Banner v-if="isError" variant="danger" title="Não foi possível carregar">
        Tente de novo em instantes.
      </Banner>

      <SurfaceCard v-else-if="isPending" :padding="false">
        <div class="flex flex-col gap-3 p-5">
          <Skeleton class="h-12" />
          <Skeleton class="h-12" />
        </div>
      </SurfaceCard>

      <SurfaceCard v-else-if="types.length === 0" :padding="false">
        <EmptyState
          title="Nenhum tipo ainda"
          description="Cada tipo pertence a uma marca."
        >
          <template #action>
            <Button @click="router.push({ name: 'product-types-new' })">Novo tipo</Button>
          </template>
        </EmptyState>
      </SurfaceCard>

      <SurfaceCard v-else :padding="false">
        <div class="divide-y divide-border-divider px-5 py-2">
          <ListCard
            v-for="type in types"
            :key="type.id"
            :title="type.name"
            :meta="type.brand?.name"
            :badge="type.is_active ? '' : 'Inativo'"
            badge-variant="muted"
            @action="router.push({ name: 'product-types-show', params: { id: String(type.id) } })"
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
