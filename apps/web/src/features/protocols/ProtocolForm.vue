<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { useForm } from 'vee-validate'
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
import MaskedBox from '@/components/ui/MaskedBox.vue'
import SearchField from '@/components/ui/SearchField.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Switch from '@/components/ui/Switch.vue'
import Textarea from '@/components/ui/Textarea.vue'
import { getProduct, listProducts } from '@/features/products/api'
import {
  clearProtocolDraft,
  loadProtocolDraft,
  saveProtocolDraft,
  type ProtocolDraft,
} from '@/features/protocols/draft'
import {
  computedTotals,
  emptyProtocolForm,
  lineSale,
  moneyInput,
  parseQuantity,
  protocolFormSchema,
  protocolToFormValues,
  protocolToItemDrafts,
  toProtocolSavePayload,
  type ProtocolFormValues,
  type ProtocolItemDraft,
} from '@/features/protocols/schema'
import { formatBRL } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { Product } from '@/types/product'
import type { Protocol, ProtocolSavePayload } from '@/types/protocol'

const props = defineProps<{
  protocol?: Protocol | null
  protocolId?: number
  submitLabel?: string
  loading?: boolean
}>()

const emit = defineEmits<{
  submit: [payload: ProtocolSavePayload]
  cancel: []
}>()

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const items = ref<ProtocolItemDraft[]>([])
const itemsError = ref('')
const suggestedDirty = ref(false)
const minDirty = ref(false)
const searchInput = ref('')
const q = ref('')
const addQuantity = ref('1')
let searchTimer: ReturnType<typeof setTimeout> | undefined
let appliedSelectProduct: number | null = null

const { defineField, handleSubmit, errors, setErrors, resetForm, setFieldValue, values } = useForm({
  validationSchema: protocolFormSchema,
  initialValues: emptyProtocolForm(),
})

const [name, nameAttrs] = defineField('name')
const [description, descriptionAttrs] = defineField('description')
const [suggestedPrice] = defineField('suggested_price')
const [minPrice] = defineField('min_price')
const [specialPrice, specialAttrs] = defineField('special_price')
const [isActive] = defineField('is_active')

const totals = computed(() => computedTotals(items.value))

watch(searchInput, (value) => {
  window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(() => {
    q.value = value.trim()
  }, 300)
})

onUnmounted(() => {
  window.clearTimeout(searchTimer)
})

const canSearchProducts = computed(() => auth.can('products.view'))

const searchQuery = useQuery({
  queryKey: ['products', 'protocol-pick', q],
  queryFn: () =>
    listProducts({
      q: q.value,
      page: 1,
      is_active: true,
    }),
  enabled: computed(() => canSearchProducts.value && q.value.length > 0),
})

const searchHits = computed(() => {
  const ids = new Set(items.value.map((item) => item.product_id))
  return (searchQuery.data.value?.data ?? []).filter((product) => !ids.has(product.id))
})

function applyDraft(draft: ProtocolDraft) {
  resetForm({
    values: {
      name: draft.name,
      description: draft.description,
      suggested_price: draft.suggested_price,
      min_price: draft.min_price,
      special_price: draft.special_price,
      is_active: draft.is_active,
    },
  })
  suggestedDirty.value = draft.suggestedDirty
  minDirty.value = draft.minDirty
  items.value = draft.items
  addQuantity.value = draft.addQuantity || '1'
}

function fillFromProtocol(protocol: Protocol | null | undefined) {
  suggestedDirty.value = Boolean(protocol?.suggested_price_is_manual)
  minDirty.value = Boolean(protocol?.min_price_is_manual)
  if (!protocol) {
    resetForm({ values: emptyProtocolForm() })
    items.value = []
    return
  }
  resetForm({ values: protocolToFormValues(protocol) })
  items.value = protocolToItemDrafts(protocol)
}

watch(
  () => props.protocol,
  (protocol) => {
    const draft = loadProtocolDraft(props.protocolId)
    if (draft) {
      applyDraft(draft)
      return
    }
    fillFromProtocol(protocol)
  },
  { immediate: true },
)

watch(
  items,
  (list) => {
    if (!suggestedDirty.value) {
      setFieldValue('suggested_price', list.length ? moneyInput(computedTotals(list).saleTotal) : '')
    }
    if (!minDirty.value) {
      setFieldValue('min_price', list.length ? moneyInput(computedTotals(list).minTotal) : '')
    }
  },
  { deep: true },
)

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
      addItem(product, '1')
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

function addItem(product: Product, qty: string) {
  if (items.value.some((item) => item.product_id === product.id)) {
    toast.info('Este produto já está no protocolo.')
    return
  }
  const quantity = parseQuantity(qty) > 0 ? qty.trim() : '1'
  items.value = [...items.value, { product_id: product.id, quantity, product }]
  itemsError.value = ''
  searchInput.value = ''
  q.value = ''
  addQuantity.value = '1'
}

function removeItem(productId: number) {
  items.value = items.value.filter((item) => item.product_id !== productId)
}

function onSuggestedUpdate(value: string) {
  suggestedPrice.value = value
  suggestedDirty.value = true
}

function onMinUpdate(value: string) {
  minPrice.value = value
  minDirty.value = true
}

function snapshotDraft(): ProtocolDraft {
  return {
    name: values.name ?? '',
    description: values.description ?? '',
    suggested_price: values.suggested_price ?? '',
    min_price: values.min_price ?? '',
    special_price: values.special_price ?? '',
    is_active: Boolean(values.is_active),
    suggestedDirty: suggestedDirty.value,
    minDirty: minDirty.value,
    items: items.value,
    addQuantity: addQuantity.value,
  }
}

function goCreateProduct() {
  saveProtocolDraft(props.protocolId, snapshotDraft())
  void router.push({
    name: 'products-new',
    query: { returnTo: route.path },
  })
}

function onSearchEnter(value: string) {
  q.value = value.trim()
}

function itemUnit(item: ProtocolItemDraft) {
  const unit = item.product.unit_of_measure
  return unit ? `${unit.name} (${unit.symbol})` : ''
}

function productMeta(product: Product) {
  return [product.sku, product.brand?.name, product.unit_of_measure?.symbol].filter(Boolean).join(' · ')
}

const onSubmit = handleSubmit((formValues: ProtocolFormValues) => {
  if (items.value.length === 0) {
    itemsError.value = 'Inclua pelo menos um produto.'
    return
  }
  const invalidQty = items.value.some((item) => !(parseQuantity(item.quantity) > 0))
  if (invalidQty) {
    itemsError.value = 'Informe uma quantidade maior que zero.'
    return
  }
  itemsError.value = ''
  emit(
    'submit',
    toProtocolSavePayload(formValues, items.value, {
      suggestedDirty: suggestedDirty.value,
      minDirty: minDirty.value,
    }),
  )
})

function applyErrors(incoming: Record<string, string>) {
  const mapped: Record<string, string> = {}
  let itemMessage = ''
  for (const [field, message] of Object.entries(incoming)) {
    if (field === 'items' || field.startsWith('items.')) {
      itemMessage = message
      continue
    }
    mapped[field] = message
  }
  if (itemMessage) {
    itemsError.value = itemMessage
  }
  setErrors(mapped)
}

defineExpose({
  setErrors: applyErrors,
  clearDraft: () => clearProtocolDraft(props.protocolId),
})
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
    <FormField label="Nome" :error="errors.name" html-for="protocol-name">
      <template #default="{ invalid }">
        <Input id="protocol-name" v-model="name" v-bind="nameAttrs" type="text" :invalid="invalid" />
      </template>
    </FormField>

    <FormField label="Descrição" :error="errors.description" html-for="protocol-description">
      <template #default="{ invalid }">
        <Textarea
          id="protocol-description"
          v-model="description"
          v-bind="descriptionAttrs"
          :invalid="invalid"
        />
      </template>
    </FormField>

    <Switch v-model="isActive" label="Ativo" />

    <h2>Itens</h2>
    <p class="text-[13px] text-muted">Busque um produto e informe a quantidade na unidade dele.</p>

    <SearchField
      v-model="searchInput"
      placeholder="Nome ou SKU do produto"
      :disabled="!canSearchProducts"
      @search="onSearchEnter"
    />

    <FormField label="Quantidade" html-for="protocol-add-qty">
      <Input
        id="protocol-add-qty"
        v-model="addQuantity"
        type="text"
        inputmode="decimal"
      />
    </FormField>

    <PermissionGate permission="products.create">
      <Button variant="ghost" type="button" @click="goCreateProduct">Novo produto</Button>
    </PermissionGate>

    <Banner v-if="!canSearchProducts" variant="warning" title="Sem busca de produtos">
      Você pode cadastrar um produto e voltar para incluí-lo no protocolo.
    </Banner>

    <SurfaceCard v-else-if="q && searchQuery.isPending" :padding="false">
      <div class="flex flex-col gap-3 p-5">
        <Skeleton class="h-12" />
        <Skeleton class="h-12" />
      </div>
    </SurfaceCard>

    <SurfaceCard v-else-if="q && searchHits.length === 0" :padding="false">
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

    <SurfaceCard v-else-if="searchHits.length > 0" :padding="false">
      <div class="divide-y divide-border-divider px-5 py-2">
        <ListCard
          v-for="product in searchHits"
          :key="product.id"
          :title="product.name"
          :meta="productMeta(product)"
          @action="addItem(product, addQuantity)"
        />
      </div>
    </SurfaceCard>

    <InlineAlert v-if="itemsError">{{ itemsError }}</InlineAlert>

    <SurfaceCard v-if="items.length > 0" :padding="false">
      <div class="divide-y divide-border-divider px-5 py-2">
        <ItemLineRow
          v-for="item in items"
          :key="item.product_id"
          v-model:quantity="item.quantity"
          :title="item.product.name"
          :unit="itemUnit(item)"
          :line-sale="lineSale(item)"
          :quantity-id="`protocol-item-qty-${item.product_id}`"
          @remove="removeItem(item.product_id)"
        />
      </div>
    </SurfaceCard>

    <h2>Preços</h2>
    <FormField label="Custo total" hint="Soma dos custos dos produtos. Somente leitura.">
      <MaskedBox :value="formatBRL(totals.totalCost)" />
    </FormField>
    <FormField label="Preços de tabela" hint="Se os itens fossem vendidos avulsos. Somente leitura.">
      <MaskedBox :value="formatBRL(totals.saleTotal)" />
    </FormField>
    <FormField
      label="Valor sugerido"
      hint="Começa pela soma dos preços de venda. Pode editar."
      :error="errors.suggested_price"
      html-for="protocol-suggested"
    >
      <template #default="{ invalid }">
        <Input
          id="protocol-suggested"
          :model-value="suggestedPrice"
          type="text"
          inputmode="decimal"
          :invalid="invalid"
          @update:model-value="onSuggestedUpdate"
        />
      </template>
    </FormField>
    <FormField
      label="Preço mínimo"
      hint="Piso do pacote. Pode editar."
      :error="errors.min_price"
      html-for="protocol-min"
    >
      <template #default="{ invalid }">
        <Input
          id="protocol-min"
          :model-value="minPrice"
          type="text"
          inputmode="decimal"
          :invalid="invalid"
          @update:model-value="onMinUpdate"
        />
      </template>
    </FormField>
    <FormField
      label="Condição especial"
      hint="Opcional. Segundo valor para facilitar a venda."
      :error="errors.special_price"
      html-for="protocol-special"
    >
      <template #default="{ invalid }">
        <Input
          id="protocol-special"
          v-model="specialPrice"
          v-bind="specialAttrs"
          type="text"
          inputmode="decimal"
          :invalid="invalid"
        />
      </template>
    </FormField>

    <div class="mt-2 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
      <Button variant="ghost" type="button" :disabled="loading" @click="emit('cancel')">
        Cancelar
      </Button>
      <Button type="submit" :loading="loading">
        {{ submitLabel ?? 'Salvar' }}
      </Button>
    </div>
  </form>
</template>
