<script setup lang="ts">
import { useForm } from 'vee-validate'
import { ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import Switch from '@/components/ui/Switch.vue'
import { slugifyCode } from '@/features/payments/labels'
import { cardBrandFormSchema, toCardBrandPayload } from '@/features/payments/schema'
import type { CardBrand, CardBrandPayload } from '@/types/sale'

const props = defineProps<{
  brand?: CardBrand | null
  submitLabel?: string
  loading?: boolean
}>()

const emit = defineEmits<{
  submit: [payload: CardBrandPayload]
  cancel: []
}>()

const codeTouched = ref(Boolean(props.brand))

const { defineField, handleSubmit, errors, setErrors, resetForm, setFieldValue } = useForm({
  validationSchema: cardBrandFormSchema,
  initialValues: { name: '', code: '', is_active: true },
})

const [name, nameAttrs] = defineField('name')
const [code, codeAttrs] = defineField('code')
const [isActive] = defineField('is_active')

watch(
  () => props.brand,
  (brand) => {
    codeTouched.value = Boolean(brand)
    resetForm({
      values: {
        name: brand?.name ?? '',
        code: brand?.code ?? '',
        is_active: brand?.is_active ?? true,
      },
    })
  },
  { immediate: true },
)

watch(name, (value) => {
  if (props.brand || codeTouched.value) {
    return
  }
  setFieldValue('code', slugifyCode(value ?? ''))
})

function onCodeInput() {
  codeTouched.value = true
}

const onSubmit = handleSubmit((values) => {
  emit('submit', toCardBrandPayload(values))
})

defineExpose({ setErrors })
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
    <FormField label="Nome" :error="errors.name" html-for="card-brand-name">
      <template #default="{ invalid }">
        <Input id="card-brand-name" v-model="name" v-bind="nameAttrs" type="text" :invalid="invalid" />
      </template>
    </FormField>
    <FormField
      label="Código"
      hint="Identificador único na clínica, ex. visa."
      :error="errors.code"
      html-for="card-brand-code"
    >
      <template #default="{ invalid }">
        <Input
          id="card-brand-code"
          v-model="code"
          v-bind="codeAttrs"
          type="text"
          :invalid="invalid"
          @update:model-value="onCodeInput"
        />
      </template>
    </FormField>
    <Switch v-model="isActive" label="Ativa" />
    <div class="mt-2 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
      <Button variant="ghost" type="button" :disabled="loading" @click="emit('cancel')">Cancelar</Button>
      <Button type="submit" :loading="loading">{{ submitLabel ?? 'Salvar' }}</Button>
    </div>
  </form>
</template>
