<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed, onUnmounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import StockStatusBadge from '@/components/patterns/StockStatusBadge.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ListCard from '@/components/ui/ListCard.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Pagination from '@/components/ui/Pagination.vue'
import SearchField from '@/components/ui/SearchField.vue'
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Switch from '@/components/ui/Switch.vue'
import { listProductTypes } from '@/features/catalog/api'
import CatalogShortcuts from '@/features/products/CatalogShortcuts.vue'
import { listProducts } from '@/features/products/api'
import { formatQty } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import type { Product } from '@/types/product'

const router = useRouter()
const auth = useAuthStore()

const searchInput = ref('')
const q = ref('')
const page = ref(1)
const activeOnly = ref(true)
const lowStock = ref(false)
const typeFilter = ref('all')
let searchTimer: ReturnType<typeof setTimeout> | undefined

watch([q, activeOnly, lowStock, typeFilter], () => {
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

const canLoadTypes = computed(
  () => auth.can('products.view') || auth.can('product_types.manage'),
)

const typesQuery = useQuery({
  queryKey: ['product-types', 'filter'],
  queryFn: () => listProductTypes({ page: 1 }),
  enabled: canLoadTypes,
})

const typeOptions = computed(() => [
  { value: 'all', label: 'Todos os tipos' },
  ...(typesQuery.data.value?.data ?? []).map((type) => ({
    value: String(type.id),
    label: type.brand?.name ? `${type.name} · ${type.brand.name}` : type.name,
  })),
])

const {
  data: listData,
  isPending,
  isError,
  isFetching,
} = useQuery({
  queryKey: ['products', q, page, activeOnly, lowStock, typeFilter],
  queryFn: () =>
    listProducts({
      q: q.value || undefined,
      page: page.value,
      is_active: activeOnly.value ? true : undefined,
      low_stock: lowStock.value || undefined,
      product_type_id: typeFilter.value && typeFilter.value !== 'all' ? Number(typeFilter.value) : undefined,
    }),
  enabled: computed(() => auth.can('products.view')),
})

const products = computed(() => listData.value?.data ?? [])
const lastPage = computed(() => listData.value?.meta.last_page ?? 1)
const total = computed(() => listData.value?.meta.total ?? 0)

function productMeta(product: Product) {
  const unit = product.unit_of_measure?.symbol
  const stock = unit
    ? `${formatQty(product.stock_quantity)} ${unit}`
    : formatQty(product.stock_quantity)
  return [product.product_type?.name, product.brand?.name, stock].filter(Boolean).join(' · ')
}

function openProduct(id: number) {
  void router.push({ name: 'products-show', params: { id: String(id) } })
}

function goNew() {
  void router.push({ name: 'products-new' })
}

function onSearchEnter(value: string) {
  q.value = value.trim()
}

const emptyTitle = computed(() => {
  const filtered =
    Boolean(q.value) || lowStock.value || (typeFilter.value !== '' && typeFilter.value !== 'all')
  return filtered ? 'Nenhum produto encontrado' : 'Nenhum produto ainda'
})
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader title="Produtos" :description="total ? `${total} no catálogo` : undefined">
      <template #actions>
        <PermissionGate permission="products.create">
          <Button @click="goNew">Novo</Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="!auth.can('products.view')" variant="danger" title="Sem permissão">
      Você não pode ver o catálogo de produtos.
    </Banner>

    <template v-else>
      <CatalogShortcuts />

      <SearchField
        v-model="searchInput"
        placeholder="Nome ou SKU"
        @search="onSearchEnter"
      />

      <Switch v-model="activeOnly" label="Somente ativos" />
      <Switch v-model="lowStock" label="Somente estoque baixo" />

      <Select
        v-if="typeOptions.length > 1"
        v-model="typeFilter"
        :options="typeOptions"
        placeholder="Tipo"
      />

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

      <SurfaceCard v-else-if="products.length === 0" :padding="false">
        <EmptyState
          :title="emptyTitle"
          :description="q ? 'Tente outro nome ou SKU.' : 'Cadastre o primeiro produto da clínica.'"
        >
          <template v-if="!q" #action>
            <PermissionGate permission="products.create">
              <Button @click="goNew">Novo produto</Button>
            </PermissionGate>
          </template>
        </EmptyState>
      </SurfaceCard>

      <SurfaceCard v-else :padding="false">
        <div class="divide-y divide-border-divider px-5 py-2">
          <ListCard
            v-for="product in products"
            :key="product.id"
            :title="product.name"
            :meta="productMeta(product)"
            :badge="product.is_active ? '' : 'Inativo'"
            badge-variant="muted"
            @action="openProduct(product.id)"
          >
            <template v-if="product.is_low_stock" #status>
              <StockStatusBadge :is-low-stock="true" :stock-quantity="product.stock_quantity" />
            </template>
          </ListCard>
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
