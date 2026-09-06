<script setup lang="ts">
import { useForm } from 'vee-validate'
import { computed, watch } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Switch from '@/components/ui/Switch.vue'
import { listBrands } from '@/features/catalog/api'
import { productTypeFormSchema } from '@/features/catalog/schema'
import type { ProductType, ProductTypePayload } from '@/types/catalog'

const props = defineProps<{
  productType?: ProductType | null
  submitLabel?: string
  loading?: boolean
}>()

const emit = defineEmits<{
  submit: [payload: ProductTypePayload]
  cancel: []
}>()

const { defineField, handleSubmit, errors, setErrors, resetForm } = useForm({
  validationSchema: productTypeFormSchema,
  initialValues: { brand_id: '', name: '', is_active: true },
})

const [brandId] = defineField('brand_id')
const [name, nameAttrs] = defineField('name')
const [isActive] = defineField('is_active')

const brandsQuery = useQuery({
  queryKey: ['brands', 'active'],
  queryFn: () => listBrands({ active_only: true, page: 1 }),
})

const brandOptions = computed(() => {
  const list = [...(brandsQuery.data.value?.data ?? [])]
  const current = props.productType?.brand
  if (current && !list.some((brand) => brand.id === current.id)) {
    list.unshift(current)
  }
  return list.map((brand) => ({ value: String(brand.id), label: brand.name }))
})

watch(
  () => props.productType,
  (type) => {
    resetForm({
      values: {
        brand_id: type ? String(type.brand_id) : '',
        name: type?.name ?? '',
        is_active: type?.is_active ?? true,
      },
    })
  },
  { immediate: true },
)

const onSubmit = handleSubmit((values) => {
  emit('submit', {
    brand_id: Number(values.brand_id),
    name: values.name.trim(),
    is_active: values.is_active,
  })
})

defineExpose({ setErrors })
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
    <FormField label="Marca" :error="errors.brand_id" html-for="type-brand">
      <template #default="{ invalid }">
        <Select
          id="type-brand"
          v-model="brandId"
          :options="brandOptions"
          placeholder="Selecionar marca"
          :invalid="invalid"
        />
      </template>
    </FormField>
    <FormField label="Nome" :error="errors.name" html-for="type-name">
      <template #default="{ invalid }">
        <Input id="type-name" v-model="name" v-bind="nameAttrs" type="text" :invalid="invalid" />
      </template>
    </FormField>
    <Switch v-model="isActive" label="Ativo" />
    <div class="mt-2 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
      <Button variant="ghost" type="button" :disabled="loading" @click="emit('cancel')">Cancelar</Button>
      <Button type="submit" :loading="loading">{{ submitLabel ?? 'Salvar' }}</Button>
    </div>
  </form>
</template>
