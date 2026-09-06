<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ItemLineRow from '@/components/patterns/ItemLineRow.vue'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import Input from '@/components/ui/Input.vue'
import ListCard from '@/components/ui/ListCard.vue'
import SearchField from '@/components/ui/SearchField.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { getProduct, listProducts } from '@/features/products/api'
import { listProtocols } from '@/features/protocols/api'
import { itemIsBelowMin, lineTotal, parseQuantity, type SaleItemDraft } from '@/features/sales/schema'
import { formatBRL } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { Product } from '@/types/product'
import type { Protocol } from '@/types/protocol'
import type { SaleProtocolReference } from '@/types/sale'

const items = defineModel<SaleItemDraft[]>('items', { required: true })

defineProps<{
  protocolReferences?: SaleProtocolReference[]
  error?: string
}>()

const emit = defineEmits<{
  applyProtocol: [protocolId: number]
}>()

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const productSearchInput = ref('')
const productQ = ref('')
const protocolSearchInput = ref('')
const protocolQ = ref('')
const addQuantity = ref('1')
let productTimer: ReturnType<typeof setTimeout> | undefined
let protocolTimer: ReturnType<typeof setTimeout> | undefined
let appliedSelectProduct: number | null = null

watch(productSearchInput, (value) => {
  window.clearTimeout(productTimer)
  productTimer = window.setTimeout(() => {
    productQ.value = value.trim()
  }, 300)
})

watch(protocolSearchInput, (value) => {
  window.clearTimeout(protocolTimer)
  protocolTimer = window.setTimeout(() => {
    protocolQ.value = value.trim()
  }, 300)
})

onUnmounted(() => {
  window.clearTimeout(productTimer)
  window.clearTimeout(protocolTimer)
})

const canSearchProducts = computed(() => auth.can('products.view') || auth.can('sales.view'))
const canSearchProtocols = computed(() => auth.can('protocols.view') || auth.can('sales.view'))

const productQuery = useQuery({
  queryKey: ['products', 'sale-pick', productQ],
  queryFn: () => listProducts({ q: productQ.value, page: 1, is_active: true }),
  enabled: computed(() => canSearchProducts.value && productQ.value.length > 0),
})

const protocolQuery = useQuery({
  queryKey: ['protocols', 'sale-pick', protocolQ],
  queryFn: () => listProtocols({ q: protocolQ.value, page: 1, is_active: true }),
  enabled: computed(() => canSearchProtocols.value && protocolQ.value.length > 0),
})

const productHits = computed(() => productQuery.data.value?.data ?? [])
const protocolHits = computed(() => protocolQuery.data.value?.data ?? [])

const selectProductId = computed(() => {
  const raw = route.query.selectProduct
  const value = Array.isArray(raw) ? raw[0] : raw
  const parsed = Number(value)
  return Number.isInteger(parsed) && parsed > 0 ? parsed : null
})

watch(
  selectProductId,
  async (id) => {
    if (!id || appliedSelectProduct === id) {
      return
    }
    appliedSelectProduct = id
    try {
      const product = await getProduct(id)
      addProduct(product)
      toast.success('Produto adicionado')
    } catch {
      toast.error('Não foi possível incluir o produto.')
    } finally {
      const nextQuery = { ...route.query }
      delete nextQuery.selectProduct
      await router.replace({ path: route.path, query: nextQuery })
    }
  },
  { immediate: true },
)

function addProduct(product: Product) {
  const quantity = parseQuantity(addQuantity.value) > 0 ? addQuantity.value.trim() : '1'
  const existing = items.value.find((item) => item.product_id === product.id)
  if (existing) {
    const nextQty = parseQuantity(existing.quantity) + parseQuantity(quantity)
    existing.quantity = String(Number.isFinite(nextQty) && nextQty > 0 ? nextQty : 1)
    toast.info('Quantidade somada ao item já existente.')
  } else {
    items.value = [
      ...items.value,
      {
        product_id: product.id,
        quantity,
        unit_price: product.sale_price,
        source_protocol_id: null,
        product_name: product.name,
        product,
        min_unit_price: product.min_sale_price ?? product.cost,
      },
    ]
  }
  productSearchInput.value = ''
  productQ.value = ''
  addQuantity.value = '1'
}

function onApplyProtocol(protocol: Protocol) {
  emit('applyProtocol', protocol.id)
  protocolSearchInput.value = ''
  protocolQ.value = ''
}

function removeItem(productId: number) {
  items.value = items.value.filter((item) => item.product_id !== productId)
}

function goCreateProduct() {
  void router.push({
    name: 'products-new',
    query: { returnTo: route.path },
  })
}

function productMeta(product: Product) {
  return [product.sku, product.brand?.name, formatBRL(product.sale_price)].filter(Boolean).join(' · ')
}

function protocolMeta(protocol: Protocol) {
  return `Sugerido ${formatBRL(protocol.suggested_price)}`
}

function itemUnit(item: SaleItemDraft) {
  const unit = item.product?.unit_of_measure
  return unit ? `${unit.name} (${unit.symbol})` : ''
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <h2>Adicionar protocolo</h2>
    <p class="text-[13px] text-muted">
      Adicionar protocolo junta as quantidades aos itens já existentes. Não substitui o pacote.
    </p>

    <SearchField
      v-model="protocolSearchInput"
      placeholder="Nome do protocolo"
      :disabled="!canSearchProtocols"
      @search="protocolQ = $event.trim()"
    />

    <SurfaceCard v-if="protocolQ && protocolQuery.isPending" :padding="false">
      <div class="flex flex-col gap-3 p-5">
        <Skeleton class="h-12" />
      </div>
    </SurfaceCard>
    <SurfaceCard v-else-if="protocolQ && protocolHits.length === 0" :padding="false">
      <p class="px-5 py-4 text-[15px] text-muted">Nenhum protocolo encontrado.</p>
    </SurfaceCard>
    <SurfaceCard v-else-if="protocolHits.length > 0" :padding="false">
      <div class="divide-y divide-border-divider px-5 py-2">
        <ListCard
          v-for="protocol in protocolHits"
          :key="protocol.id"
          :title="protocol.name"
          :meta="protocolMeta(protocol)"
          @action="onApplyProtocol(protocol)"
        />
      </div>
    </SurfaceCard>

    <h2>Produtos avulsos</h2>
    <SearchField
      v-model="productSearchInput"
      placeholder="Nome ou SKU do produto"
      :disabled="!canSearchProducts"
      @search="productQ = $event.trim()"
    />
    <FormField label="Quantidade" html-for="sale-add-qty">
      <Input id="sale-add-qty" v-model="addQuantity" type="text" inputmode="decimal" />
    </FormField>
    <PermissionGate permission="products.create">
      <Button variant="ghost" type="button" @click="goCreateProduct">Novo produto</Button>
    </PermissionGate>

    <Banner v-if="!canSearchProducts" variant="warning" title="Sem busca de produtos">
      Você pode cadastrar um produto e voltar para incluí-lo na venda.
    </Banner>
    <SurfaceCard v-else-if="productQ && productQuery.isPending" :padding="false">
      <div class="flex flex-col gap-3 p-5">
        <Skeleton class="h-12" />
      </div>
    </SurfaceCard>
    <SurfaceCard v-else-if="productQ && productHits.length === 0" :padding="false">
      <div class="px-5 py-4">
        <p class="text-[15px] text-title">Nenhum produto encontrado</p>
        <p class="mt-1 text-[13px] text-muted">Cadastre o produto e volte para adicioná-lo.</p>
        <PermissionGate permission="products.create">
          <Button class="mt-3" variant="secondary" type="button" @click="goCreateProduct">
            Novo produto
          </Button>
        </PermissionGate>
      </div>
    </SurfaceCard>
    <SurfaceCard v-else-if="productHits.length > 0" :padding="false">
      <div class="divide-y divide-border-divider px-5 py-2">
        <ListCard
          v-for="product in productHits"
          :key="product.id"
          :title="product.name"
          :meta="productMeta(product)"
          @action="addProduct(product)"
        />
      </div>
    </SurfaceCard>

    <InlineAlert v-if="error">{{ error }}</InlineAlert>

    <SurfaceCard v-if="protocolReferences?.length" :padding="false">
      <div class="px-5 py-4">
        <p class="text-[13px] text-muted">Referência dos protocolos</p>
        <p
          v-for="reference in protocolReferences"
          :key="reference.id"
          class="mt-1 text-[15px] text-title"
        >
          {{ reference.name }} · sugerido {{ formatBRL(reference.suggested_price) }} · mínimo
          {{ formatBRL(reference.min_price) }}
        </p>
      </div>
    </SurfaceCard>

    <SurfaceCard v-if="items.length > 0" :padding="false">
      <div class="divide-y divide-border-divider px-5 py-2">
        <ItemLineRow
          v-for="item in items"
          :key="item.product_id"
          v-model:quantity="item.quantity"
          v-model:unit-price="item.unit_price"
          :title="item.product_name"
          :unit="itemUnit(item)"
          :line-sale="lineTotal(item)"
          :quantity-id="`sale-item-qty-${item.product_id}`"
          :price-id="`sale-item-price-${item.product_id}`"
          show-unit-price
          :price-invalid="itemIsBelowMin(item)"
          @remove="removeItem(item.product_id)"
        />
      </div>
    </SurfaceCard>
  </div>
</template>
