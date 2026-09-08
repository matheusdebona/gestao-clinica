<script setup lang="ts">
import { useForm } from 'vee-validate'
import { computed, ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import Select from '@/components/ui/Select.vue'
import Switch from '@/components/ui/Switch.vue'
import { CARD_KINDS, PAYMENT_KIND_OPTIONS, slugifyCode } from '@/features/payments/labels'
import {
  emptyPaymentMethodForm,
  paymentMethodFormSchema,
  paymentMethodToFormValues,
  toPaymentMethodPayload,
} from '@/features/payments/schema'
import type { PaymentMethod, PaymentMethodPayload } from '@/types/sale'

const props = defineProps<{
  method?: PaymentMethod | null
  submitLabel?: string
  loading?: boolean
}>()

const emit = defineEmits<{
  submit: [payload: PaymentMethodPayload]
  cancel: []
}>()

const codeTouched = ref(Boolean(props.method))

const { defineField, handleSubmit, errors, setErrors, resetForm, setFieldValue } = useForm({
  validationSchema: paymentMethodFormSchema,
  initialValues: emptyPaymentMethodForm(),
})

const [name, nameAttrs] = defineField('name')
const [code, codeAttrs] = defineField('code')
const [kind] = defineField('kind')
const [feePercent, feePercentAttrs] = defineField('fee_percent')
const [feeFixed, feeFixedAttrs] = defineField('fee_fixed')
const [isActive] = defineField('is_active')

const isCardKind = computed(() => CARD_KINDS.has(kind.value ?? ''))

watch(
  () => props.method,
  (method) => {
    codeTouched.value = Boolean(method)
    resetForm({ values: paymentMethodToFormValues(method) })
  },
  { immediate: true },
)

watch(name, (value) => {
  if (props.method || codeTouched.value) {
    return
  }
  setFieldValue('code', slugifyCode(value ?? ''))
})

function onCodeInput() {
  codeTouched.value = true
}

const onSubmit = handleSubmit((values) => {
  emit('submit', toPaymentMethodPayload(values))
})

defineExpose({ setErrors })
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
    <FormField label="Nome" :error="errors.name" html-for="method-name">
      <template #default="{ invalid }">
        <Input id="method-name" v-model="name" v-bind="nameAttrs" type="text" :invalid="invalid" />
      </template>
    </FormField>
    <FormField
      label="Código"
      hint="Identificador único na clínica, ex. pix."
      :error="errors.code"
      html-for="method-code"
    >
      <template #default="{ invalid }">
        <Input
          id="method-code"
          v-model="code"
          v-bind="codeAttrs"
          type="text"
          :invalid="invalid"
          @update:model-value="onCodeInput"
        />
      </template>
    </FormField>
    <FormField label="Tipo" :error="errors.kind" html-for="method-kind">
      <template #default="{ invalid }">
        <Select
          id="method-kind"
          v-model="kind"
          :options="PAYMENT_KIND_OPTIONS"
          placeholder="Selecionar tipo"
          :invalid="invalid"
        />
      </template>
    </FormField>
    <template v-if="!isCardKind">
      <FormField
        label="Taxa percentual"
        hint="Opcional. Deixe vazio se não houver taxa."
        :error="errors.fee_percent"
        html-for="method-fee-percent"
      >
        <template #default="{ invalid }">
          <Input
            id="method-fee-percent"
            v-model="feePercent"
            v-bind="feePercentAttrs"
            type="text"
            inputmode="decimal"
            placeholder="0"
            :invalid="invalid"
          />
        </template>
      </FormField>
      <FormField
        label="Taxa fixa"
        hint="Opcional. Ex.: boleto."
        :error="errors.fee_fixed"
        html-for="method-fee-fixed"
      >
        <template #default="{ invalid }">
          <MoneyInput
            id="method-fee-fixed"
            v-model="feeFixed"
            v-bind="feeFixedAttrs"
            :invalid="invalid"
          />
        </template>
      </FormField>
    </template>
    <Switch v-model="isActive" label="Ativo" />
    <div class="mt-2 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
      <Button variant="ghost" type="button" :disabled="loading" @click="emit('cancel')">Cancelar</Button>
      <Button type="submit" :loading="loading">{{ submitLabel ?? 'Salvar' }}</Button>
    </div>
  </form>
</template>
