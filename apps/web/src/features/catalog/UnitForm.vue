<script setup lang="ts">
import { useForm } from 'vee-validate'
import { watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import Switch from '@/components/ui/Switch.vue'
import { unitFormSchema } from '@/features/catalog/schema'
import type { UnitOfMeasure, UnitPayload } from '@/types/catalog'

const props = defineProps<{
  unit?: UnitOfMeasure | null
  submitLabel?: string
  loading?: boolean
}>()

const emit = defineEmits<{
  submit: [payload: UnitPayload]
  cancel: []
}>()

const { defineField, handleSubmit, errors, setErrors, resetForm } = useForm({
  validationSchema: unitFormSchema,
  initialValues: { name: '', symbol: '', is_active: true },
})

const [name, nameAttrs] = defineField('name')
const [symbol, symbolAttrs] = defineField('symbol')
const [isActive] = defineField('is_active')

watch(
  () => props.unit,
  (unit) => {
    resetForm({
      values: {
        name: unit?.name ?? '',
        symbol: unit?.symbol ?? '',
        is_active: unit?.is_active ?? true,
      },
    })
  },
  { immediate: true },
)

const onSubmit = handleSubmit((values) => {
  emit('submit', {
    name: values.name.trim(),
    symbol: values.symbol.trim(),
    is_active: values.is_active,
  })
})

defineExpose({ setErrors })
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
    <FormField label="Nome" :error="errors.name" html-for="unit-name">
      <template #default="{ invalid }">
        <Input id="unit-name" v-model="name" v-bind="nameAttrs" type="text" :invalid="invalid" />
      </template>
    </FormField>
    <FormField label="Símbolo" hint="Ex.: un, ml, mg." :error="errors.symbol" html-for="unit-symbol">
      <template #default="{ invalid }">
        <Input id="unit-symbol" v-model="symbol" v-bind="symbolAttrs" type="text" :invalid="invalid" />
      </template>
    </FormField>
    <Switch v-model="isActive" label="Ativa" />
    <div class="mt-2 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
      <Button variant="ghost" type="button" :disabled="loading" @click="emit('cancel')">Cancelar</Button>
      <Button type="submit" :loading="loading">{{ submitLabel ?? 'Salvar' }}</Button>
    </div>
  </form>
</template>
