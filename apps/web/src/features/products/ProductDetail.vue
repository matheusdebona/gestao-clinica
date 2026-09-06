<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import MoneyDisplay from '@/components/patterns/MoneyDisplay.vue'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import StockStatusBadge from '@/components/patterns/StockStatusBadge.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import CatalogShortcuts from '@/features/products/CatalogShortcuts.vue'
import StockAdjustDialog from '@/features/products/StockAdjustDialog.vue'
import { adjustStock, deactivateProduct, getProduct, updateProduct } from '@/features/products/api'
import { formatQty } from '@/lib/formatters'
import { useToastStore } from '@/stores/toast'
import type { StockAdjustPayload } from '@/types/product'
import { ApiError } from '@/types/user'

const props = defineProps<{
  productId: number
}>()

const router = useRouter()
const toast = useToastStore()
const queryClient = useQueryClient()
const confirmOpen = ref(false)
const adjustOpen = ref(false)
const adjustFormRef = ref<{ setErrors: (errors: Record<string, string>) => void } | null>(null)

const idRef = computed(() => props.productId)

const { data: product, isPending, isError } = useQuery({
  queryKey: ['products', idRef],
  queryFn: () => getProduct(idRef.value),
})

const stockLabel = computed(() => {
  if (!product.value) {
    return '—'
  }
  const unit = product.value.unit_of_measure?.symbol
  const qty = formatQty(product.value.stock_quantity)
  return unit ? `${qty} ${unit}` : qty
})

const deactivateMutation = useMutation({
  mutationFn: () => deactivateProduct(props.productId),
  onSuccess: async () => {
    toast.success('Produto desativado')
    await queryClient.invalidateQueries({ queryKey: ['products'] })
    await router.push({ name: 'products' })
  },
  onError: (error) => {
    if (error instanceof ApiError && error.status === 403) {
      toast.error('Sem permissão para desativar.')
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível desativar.')
  },
})

const { mutate: reactivate, isPending: reactivating } = useMutation({
  mutationFn: () => updateProduct(props.productId, { is_active: true }),
  onSuccess: async () => {
    toast.success('Produto reativado')
    await queryClient.invalidateQueries({ queryKey: ['products'] })
  },
  onError: (error) => {
    if (error instanceof ApiError && error.status === 403) {
      toast.error('Sem permissão para atualizar.')
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível reativar.')
  },
})

const { mutate: adjust, isPending: adjusting } = useMutation({
  mutationFn: (payload: StockAdjustPayload) => adjustStock(props.productId, payload),
  onSuccess: async () => {
    toast.success('Estoque atualizado')
    adjustOpen.value = false
    await queryClient.invalidateQueries({ queryKey: ['products'] })
  },
  onError: (error) => {
    if (error instanceof ApiError && error.status === 422) {
      const mapped: Record<string, string> = {}
      for (const [field, messages] of Object.entries(error.errors)) {
        mapped[field] = messages[0] ?? error.message
      }
      adjustFormRef.value?.setErrors(mapped)
      if (!Object.keys(mapped).length) {
        toast.error(error.message)
      }
      return
    }
    if (error instanceof ApiError && error.status === 403) {
      toast.error('Sem permissão para ajustar estoque.')
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível ajustar o estoque.')
  },
})

function goEdit() {
  void router.push({ name: 'products-edit', params: { id: String(props.productId) } })
}

function goBack() {
  void router.push({ name: 'products' })
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="product?.name ?? 'Produto'">
      <template #actions>
        <Button variant="ghost" @click="goBack">Voltar</Button>
        <PermissionGate v-if="product?.is_active" permission="products.update">
          <Button variant="secondary" @click="goEdit">Editar</Button>
        </PermissionGate>
        <PermissionGate permission="products.adjust_stock">
          <Button variant="secondary" @click="adjustOpen = true">Ajustar estoque</Button>
        </PermissionGate>
        <PermissionGate v-if="product?.is_active" permission="products.delete">
          <Button variant="destructive" @click="confirmOpen = true">Desativar</Button>
        </PermissionGate>
        <PermissionGate v-else-if="product" permission="products.update">
          <Button variant="secondary" :loading="reactivating" @click="reactivate()">
            Reativar
          </Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <CatalogShortcuts />

    <Banner v-if="product && !product.is_active" variant="warning" title="Inativo">
      Este produto está desativado e não aparece na lista padrão.
    </Banner>

    <Banner v-if="isError" variant="danger" title="Não foi possível carregar">
      O produto pode ter sido removido ou você não tem permissão.
    </Banner>

    <SurfaceCard v-else-if="isPending">
      <div class="flex flex-col gap-3">
        <Skeleton class="h-6 w-40" />
        <Skeleton class="h-5 w-56" />
        <Skeleton class="h-5 w-32" />
      </div>
    </SurfaceCard>

    <SurfaceCard v-else-if="product">
      <dl class="flex flex-col gap-4">
        <div>
          <dt class="text-[13px] text-muted">SKU</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ product.sku || '—' }}</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Marca</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ product.brand?.name ?? '—' }}</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Tipo</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ product.product_type?.name ?? '—' }}</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Unidade</dt>
          <dd class="mt-0.5 text-[15px] text-title">
            {{
              product.unit_of_measure
                ? `${product.unit_of_measure.name} (${product.unit_of_measure.symbol})`
                : '—'
            }}
          </dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Estoque</dt>
          <dd class="mt-0.5 flex flex-wrap items-center gap-2 text-[15px] text-title">
            <span>{{ stockLabel }}</span>
            <StockStatusBadge
              :is-low-stock="product.is_low_stock"
              :stock-quantity="product.stock_quantity"
            />
          </dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Estoque mínimo</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ formatQty(product.min_stock) }}</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Custo médio</dt>
          <dd class="mt-0.5">
            <MoneyDisplay :value="product.cost" />
          </dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Preço de venda</dt>
          <dd class="mt-0.5">
            <MoneyDisplay :value="product.sale_price" />
          </dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Preço mínimo</dt>
          <dd class="mt-0.5">
            <MoneyDisplay :value="product.min_sale_price" />
          </dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Margem unitária</dt>
          <dd class="mt-0.5">
            <MoneyDisplay :value="product.unit_margin" />
          </dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Prazo de reposição</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ product.lead_time_days }} dias</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Finalidade</dt>
          <dd class="mt-0.5 whitespace-pre-wrap text-[15px] text-title">
            {{ product.purpose || '—' }}
          </dd>
        </div>
      </dl>
    </SurfaceCard>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Desativar este produto?"
      description="O cadastro permanece no histórico. Você pode reativar depois."
      confirm-label="Desativar"
      @confirm="deactivateMutation.mutate()"
    />

    <StockAdjustDialog
      ref="adjustFormRef"
      v-model:open="adjustOpen"
      :loading="adjusting"
      @submit="adjust"
    />
  </div>
</template>
