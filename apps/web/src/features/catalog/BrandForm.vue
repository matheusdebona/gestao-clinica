<script setup lang="ts">
import { useForm } from 'vee-validate'
import { watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import Switch from '@/components/ui/Switch.vue'
import { brandFormSchema } from '@/features/catalog/schema'
import type { Brand, BrandPayload } from '@/types/catalog'

const props = defineProps<{
  brand?: Brand | null
  submitLabel?: string
  loading?: boolean
}>()

const emit = defineEmits<{
  submit: [payload: BrandPayload]
  cancel: []
}>()

const { defineField, handleSubmit, errors, setErrors, resetForm } = useForm({
  validationSchema: brandFormSchema,
  initialValues: { name: '', is_active: true },
})

const [name, nameAttrs] = defineField('name')
const [isActive] = defineField('is_active')

watch(
  () => props.brand,
  (brand) => {
    resetForm({
      values: {
        name: brand?.name ?? '',
        is_active: brand?.is_active ?? true,
      },
    })
  },
  { immediate: true },
)

const onSubmit = handleSubmit((values) => {
  emit('submit', { name: values.name.trim(), is_active: values.is_active })
})

defineExpose({ setErrors })
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
    <FormField label="Nome" :error="errors.name" html-for="brand-name">
      <template #default="{ invalid }">
        <Input id="brand-name" v-model="name" v-bind="nameAttrs" type="text" :invalid="invalid" />
      </template>
    </FormField>
    <Switch v-model="isActive" label="Ativa" />
    <div class="mt-2 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
      <Button variant="ghost" type="button" :disabled="loading" @click="emit('cancel')">Cancelar</Button>
      <Button type="submit" :loading="loading">{{ submitLabel ?? 'Salvar' }}</Button>
    </div>
  </form>
</template>
