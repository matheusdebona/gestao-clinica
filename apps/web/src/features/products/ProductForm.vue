<script setup lang="ts">
import { useForm } from 'vee-validate'
import { computed, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import AppDialog from '@/components/ui/AppDialog.vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import MaskedBox from '@/components/ui/MaskedBox.vue'
import Select from '@/components/ui/Select.vue'
import Switch from '@/components/ui/Switch.vue'
import Textarea from '@/components/ui/Textarea.vue'
import {
  createBrand,
  createProductType,
  createUnit,
  listBrands,
  listProductTypes,
  listUnits,
} from '@/features/catalog/api'
import CatalogShortcuts from '@/features/products/CatalogShortcuts.vue'
import {
  emptyProductForm,
  productFormSchema,
  productToFormValues,
  toProductPayload,
  type ProductFormValues,
} from '@/features/products/schema'
import { formatBRL, formatQty } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { Brand, ProductType, UnitOfMeasure } from '@/types/catalog'
import type { Paginated } from '@/types/pagination'
import type { Product, ProductPayload } from '@/types/product'
import { ApiError } from '@/types/user'

const props = defineProps<{
  product?: Product | null
  submitLabel?: string
  loading?: boolean
}>()

const emit = defineEmits<{
  submit: [payload: ProductPayload]
  cancel: []
}>()

const auth = useAuthStore()
const toast = useToastStore()
const queryClient = useQueryClient()
const isEdit = computed(() => Boolean(props.product))

const brandDialog = ref(false)
const typeDialog = ref(false)
const unitDialog = ref(false)
const newBrandName = ref('')
const newTypeName = ref('')
const newUnitName = ref('')
const newUnitSymbol = ref('')
const brandDialogError = ref('')
const typeDialogError = ref('')
const unitDialogError = ref('')

const { defineField, handleSubmit, errors, setErrors, resetForm, setFieldValue } = useForm({
  validationSchema: productFormSchema,
  initialValues: emptyProductForm(),
})

const [name, nameAttrs] = defineField('name')
const [sku, skuAttrs] = defineField('sku')
const [brandId] = defineField('brand_id')
const [typeId] = defineField('product_type_id')
const [unitId] = defineField('unit_of_measure_id')
const [purpose, purposeAttrs] = defineField('purpose')
const [cost, costAttrs] = defineField('cost')
const [salePrice, salePriceAttrs] = defineField('sale_price')
const [minSalePrice, minSalePriceAttrs] = defineField('min_sale_price')
const [stockQuantity, stockQuantityAttrs] = defineField('stock_quantity')
const [minStock, minStockAttrs] = defineField('min_stock')
const [leadTime, leadTimeAttrs] = defineField('lead_time_days')
const [isActive] = defineField('is_active')

const selectedBrandId = computed(() => {
  const parsed = Number(brandId.value)
  return Number.isInteger(parsed) && parsed > 0 ? parsed : null
})

const brandsQuery = useQuery({
  queryKey: ['brands', 'active'],
  queryFn: () => listBrands({ active_only: true, page: 1 }),
  enabled: computed(() => auth.can('products.view') || auth.can('brands.manage')),
})

const typesQuery = useQuery({
  queryKey: ['product-types', 'by-brand', selectedBrandId],
  queryFn: () => listProductTypes({ brand_id: selectedBrandId.value ?? undefined, active_only: true, page: 1 }),
  enabled: computed(
    () =>
      selectedBrandId.value !== null &&
      (auth.can('products.view') || auth.can('product_types.manage')),
  ),
})

const unitsQuery = useQuery({
  queryKey: ['units', 'active'],
  queryFn: () => listUnits({ active_only: true, page: 1 }),
  enabled: computed(() => auth.can('products.view') || auth.can('units.manage')),
})

function withCurrentOption<T extends { id: number; name: string }>(
  list: T[],
  current: T | null | undefined,
): T[] {
  if (!current) {
    return list
  }
  if (list.some((item) => item.id === current.id)) {
    return list
  }
  return [current, ...list]
}

const brandOptions = computed(() =>
  withCurrentOption(brandsQuery.data.value?.data ?? [], props.product?.brand ?? null).map((brand) => ({
    value: String(brand.id),
    label: brand.name,
  })),
)

const typeOptions = computed(() =>
  withCurrentOption(typesQuery.data.value?.data ?? [], props.product?.product_type ?? null).map(
    (type) => ({
      value: String(type.id),
      label: type.name,
    }),
  ),
)

const unitOptions = computed(() =>
  withCurrentOption(unitsQuery.data.value?.data ?? [], props.product?.unit_of_measure ?? null).map(
    (unit) => ({
      value: String(unit.id),
      label: `${unit.name} (${unit.symbol})`,
    }),
  ),
)

watch(
  () => props.product,
  (product) => {
    if (!product) {
      resetForm({ values: emptyProductForm() })
      return
    }
    resetForm({ values: productToFormValues(product) })
  },
  { immediate: true },
)

function prependCatalogItem<T extends { id: number }>(
  old: Paginated<T> | undefined,
  item: T,
): Paginated<T> | undefined {
  if (!old) {
    return old
  }
  if (old.data.some((row) => row.id === item.id)) {
    return old
  }
  return { ...old, data: [item, ...old.data] }
}

function onBrandUpdate(value: string) {
  if (value === brandId.value) {
    return
  }
  setFieldValue('brand_id', value)
  setFieldValue('product_type_id', '')
}

const { mutate: createBrandMutate, isPending: creatingBrand } = useMutation({
  mutationFn: () => createBrand({ name: newBrandName.value.trim() }),
  onSuccess: async (brand) => {
    brandDialog.value = false
    newBrandName.value = ''
    brandDialogError.value = ''
    toast.success('Marca cadastrada')
    queryClient.setQueryData(['brands', 'active'], (old: Paginated<Brand> | undefined) =>
      prependCatalogItem(old, brand),
    )
    setFieldValue('brand_id', String(brand.id))
    setFieldValue('product_type_id', '')
    await queryClient.invalidateQueries({ queryKey: ['brands'] })
  },
  onError: (error) => {
    if (error instanceof ApiError && error.status === 422) {
      brandDialogError.value = error.first('name') || error.message
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível cadastrar a marca.')
  },
})

const { mutate: createTypeMutate, isPending: creatingType } = useMutation({
  mutationFn: () =>
    createProductType({
      name: newTypeName.value.trim(),
      brand_id: selectedBrandId.value as number,
    }),
  onSuccess: async (type) => {
    typeDialog.value = false
    newTypeName.value = ''
    typeDialogError.value = ''
    toast.success('Tipo cadastrado')
    queryClient.setQueryData(
      ['product-types', 'by-brand', selectedBrandId.value],
      (old: Paginated<ProductType> | undefined) => prependCatalogItem(old, type),
    )
    setFieldValue('product_type_id', String(type.id))
    await queryClient.invalidateQueries({ queryKey: ['product-types'] })
  },
  onError: (error) => {
    if (error instanceof ApiError && error.status === 422) {
      typeDialogError.value = error.first('name') || error.first('brand_id') || error.message
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível cadastrar o tipo.')
  },
})

const { mutate: createUnitMutate, isPending: creatingUnit } = useMutation({
  mutationFn: () =>
    createUnit({
      name: newUnitName.value.trim(),
      symbol: newUnitSymbol.value.trim(),
    }),
  onSuccess: async (unit) => {
    unitDialog.value = false
    newUnitName.value = ''
    newUnitSymbol.value = ''
    unitDialogError.value = ''
    toast.success('Unidade cadastrada')
    queryClient.setQueryData(['units', 'active'], (old: Paginated<UnitOfMeasure> | undefined) =>
      prependCatalogItem(old, unit),
    )
    setFieldValue('unit_of_measure_id', String(unit.id))
    await queryClient.invalidateQueries({ queryKey: ['units'] })
  },
  onError: (error) => {
    if (error instanceof ApiError && error.status === 422) {
      unitDialogError.value = error.first('symbol') || error.first('name') || error.message
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível cadastrar a unidade.')
  },
})

function openTypeDialog() {
  if (!selectedBrandId.value) {
    toast.info('Selecione a marca primeiro.')
    return
  }
  typeDialogError.value = ''
  newTypeName.value = ''
  typeDialog.value = true
}

const onSubmit = handleSubmit((formValues: ProductFormValues) => {
  emit('submit', toProductPayload(formValues, !isEdit.value))
})

defineExpose({ setErrors })
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
    <CatalogShortcuts />

    <FormField label="Marca" :error="errors.brand_id" html-for="product-brand">
      <template #default="{ invalid }">
        <Select
          id="product-brand"
          :model-value="brandId"
          :options="brandOptions"
          placeholder="Selecionar marca"
          :invalid="invalid"
          @update:model-value="onBrandUpdate"
        />
      </template>
    </FormField>
    <PermissionGate permission="brands.manage">
      <Button variant="ghost" type="button" @click="brandDialog = true">Nova marca</Button>
    </PermissionGate>

    <FormField label="Tipo" :error="errors.product_type_id" html-for="product-type">
      <template #default="{ invalid }">
        <Select
          id="product-type"
          v-model="typeId"
          :options="typeOptions"
          placeholder="Selecionar tipo"
          :disabled="!selectedBrandId"
          :invalid="invalid"
        />
      </template>
    </FormField>
    <PermissionGate permission="product_types.manage">
      <Button variant="ghost" type="button" @click="openTypeDialog">Novo tipo desta marca</Button>
    </PermissionGate>

    <FormField label="Nome" :error="errors.name" html-for="product-name">
      <template #default="{ invalid }">
        <Input id="product-name" v-model="name" v-bind="nameAttrs" type="text" :invalid="invalid" />
      </template>
    </FormField>

    <FormField label="SKU" :error="errors.sku" html-for="product-sku">
      <template #default="{ invalid }">
        <Input id="product-sku" v-model="sku" v-bind="skuAttrs" type="text" :invalid="invalid" />
      </template>
    </FormField>

    <FormField label="Unidade" :error="errors.unit_of_measure_id" html-for="product-unit">
      <template #default="{ invalid }">
        <Select
          id="product-unit"
          v-model="unitId"
          :options="unitOptions"
          placeholder="Selecionar unidade"
          :invalid="invalid"
        />
      </template>
    </FormField>
    <PermissionGate permission="units.manage">
      <Button variant="ghost" type="button" @click="unitDialog = true">Nova unidade</Button>
    </PermissionGate>

    <FormField label="Finalidade" :error="errors.purpose" html-for="product-purpose">
      <template #default="{ invalid }">
        <Textarea id="product-purpose" v-model="purpose" v-bind="purposeAttrs" :invalid="invalid" />
      </template>
    </FormField>

    <FormField
      v-if="!isEdit"
      label="Custo"
      hint="Usado na entrada inicial. Depois só via ajuste de estoque."
      :error="errors.cost"
      html-for="product-cost"
    >
      <template #default="{ invalid }">
        <Input
          id="product-cost"
          v-model="cost"
          v-bind="costAttrs"
          type="text"
          inputmode="decimal"
          :invalid="invalid"
        />
      </template>
    </FormField>
    <FormField v-else label="Custo médio" hint="Altera só com entrada de estoque.">
      <MaskedBox :value="formatBRL(props.product?.cost)" />
    </FormField>

    <FormField label="Preço de venda" :error="errors.sale_price" html-for="product-sale">
      <template #default="{ invalid }">
        <Input
          id="product-sale"
          v-model="salePrice"
          v-bind="salePriceAttrs"
          type="text"
          inputmode="decimal"
          :invalid="invalid"
        />
      </template>
    </FormField>

    <FormField label="Preço mínimo" :error="errors.min_sale_price" html-for="product-min-sale">
      <template #default="{ invalid }">
        <Input
          id="product-min-sale"
          v-model="minSalePrice"
          v-bind="minSalePriceAttrs"
          type="text"
          inputmode="decimal"
          :invalid="invalid"
        />
      </template>
    </FormField>

    <FormField
      v-if="!isEdit"
      label="Estoque inicial"
      hint="Se maior que zero, vira uma entrada."
      :error="errors.stock_quantity"
      html-for="product-stock"
    >
      <template #default="{ invalid }">
        <Input
          id="product-stock"
          v-model="stockQuantity"
          v-bind="stockQuantityAttrs"
          type="text"
          inputmode="decimal"
          :invalid="invalid"
        />
      </template>
    </FormField>
    <FormField v-else label="Estoque atual" hint="Altera só com ajuste de estoque.">
      <MaskedBox :value="formatQty(props.product?.stock_quantity)" />
    </FormField>

    <FormField label="Estoque mínimo" :error="errors.min_stock" html-for="product-min-stock">
      <template #default="{ invalid }">
        <Input
          id="product-min-stock"
          v-model="minStock"
          v-bind="minStockAttrs"
          type="text"
          inputmode="decimal"
          :invalid="invalid"
        />
      </template>
    </FormField>

    <FormField
      label="Prazo de reposição (dias)"
      :error="errors.lead_time_days"
      html-for="product-lead"
    >
      <template #default="{ invalid }">
        <Input
          id="product-lead"
          v-model="leadTime"
          v-bind="leadTimeAttrs"
          type="number"
          :invalid="invalid"
        />
      </template>
    </FormField>

    <Switch v-model="isActive" label="Ativo" />

    <div class="mt-2 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
      <Button variant="ghost" type="button" :disabled="loading" @click="emit('cancel')">
        Cancelar
      </Button>
      <Button type="submit" :loading="loading">
        {{ submitLabel ?? 'Salvar' }}
      </Button>
    </div>
  </form>

  <AppDialog v-model:open="brandDialog" title="Nova marca" description="A marca fica disponível no cadastro de produtos.">
    <FormField label="Nome" :error="brandDialogError" html-for="new-brand-name">
      <template #default="{ invalid }">
        <Input id="new-brand-name" v-model="newBrandName" type="text" :invalid="invalid" />
      </template>
    </FormField>
    <template #footer>
      <Button variant="ghost" type="button" @click="brandDialog = false">Cancelar</Button>
      <Button
        type="button"
        :loading="creatingBrand"
        :disabled="!newBrandName.trim()"
        @click="createBrandMutate()"
      >
        Cadastrar
      </Button>
    </template>
  </AppDialog>

  <AppDialog v-model:open="typeDialog" title="Novo tipo" description="O tipo fica ligado à marca selecionada.">
    <FormField label="Nome" :error="typeDialogError" html-for="new-type-name">
      <template #default="{ invalid }">
        <Input id="new-type-name" v-model="newTypeName" type="text" :invalid="invalid" />
      </template>
    </FormField>
    <template #footer>
      <Button variant="ghost" type="button" @click="typeDialog = false">Cancelar</Button>
      <Button
        type="button"
        :loading="creatingType"
        :disabled="!newTypeName.trim()"
        @click="createTypeMutate()"
      >
        Cadastrar
      </Button>
    </template>
  </AppDialog>

  <AppDialog v-model:open="unitDialog" title="Nova unidade" description="Unidades não dependem da marca.">
    <div class="flex flex-col gap-4">
      <FormField label="Nome" html-for="new-unit-name">
        <Input id="new-unit-name" v-model="newUnitName" type="text" />
      </FormField>
      <FormField label="Símbolo" :error="unitDialogError" html-for="new-unit-symbol">
        <template #default="{ invalid }">
          <Input id="new-unit-symbol" v-model="newUnitSymbol" type="text" :invalid="invalid" />
        </template>
      </FormField>
    </div>
    <template #footer>
      <Button variant="ghost" type="button" @click="unitDialog = false">Cancelar</Button>
      <Button
        type="button"
        :loading="creatingUnit"
        :disabled="!newUnitName.trim() || !newUnitSymbol.trim()"
        @click="createUnitMutate()"
      >
        Cadastrar
      </Button>
    </template>
  </AppDialog>
</template>
