<script setup lang="ts">
import { useForm } from 'vee-validate'
import { ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import Switch from '@/components/ui/Switch.vue'
import { slugifyCode } from '@/features/payments/labels'
import { cardOperatorFormSchema, toCardOperatorPayload } from '@/features/payments/schema'
import type { CardOperator, CardOperatorPayload } from '@/types/sale'

const props = defineProps<{
  operator?: CardOperator | null
  submitLabel?: string
  loading?: boolean
}>()

const emit = defineEmits<{
  submit: [payload: CardOperatorPayload]
  cancel: []
}>()

const codeTouched = ref(Boolean(props.operator))

const { defineField, handleSubmit, errors, setErrors, resetForm, setFieldValue } = useForm({
  validationSchema: cardOperatorFormSchema,
  initialValues: { name: '', code: '', auto_anticipate: false, is_active: true },
})

const [name, nameAttrs] = defineField('name')
const [code, codeAttrs] = defineField('code')
const [autoAnticipate] = defineField('auto_anticipate')
const [isActive] = defineField('is_active')

watch(
  () => props.operator,
  (operator) => {
    codeTouched.value = Boolean(operator)
    resetForm({
      values: {
        name: operator?.name ?? '',
        code: operator?.code ?? '',
        auto_anticipate: operator?.auto_anticipate ?? false,
        is_active: operator?.is_active ?? true,
      },
    })
  },
  { immediate: true },
)

watch(name, (value) => {
  if (props.operator || codeTouched.value) {
    return
  }
  setFieldValue('code', slugifyCode(value ?? ''))
})

function onCodeInput() {
  codeTouched.value = true
}

const onSubmit = handleSubmit((values) => {
  emit('submit', toCardOperatorPayload(values))
})

defineExpose({ setErrors })
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
    <FormField label="Nome" :error="errors.name" html-for="card-operator-name">
      <template #default="{ invalid }">
        <Input id="card-operator-name" v-model="name" v-bind="nameAttrs" type="text" :invalid="invalid" />
      </template>
    </FormField>
    <FormField
      label="Código"
      hint="Opcional. Identificador único na clínica, ex. stone."
      :error="errors.code"
      html-for="card-operator-code"
    >
      <template #default="{ invalid }">
        <Input
          id="card-operator-code"
          v-model="code"
          v-bind="codeAttrs"
          type="text"
          :invalid="invalid"
          @update:model-value="onCodeInput"
        />
      </template>
    </FormField>
    <Switch v-model="autoAnticipate" label="Antecipação automática" />
    <Switch v-model="isActive" label="Ativa" />
    <div class="mt-2 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
      <Button variant="ghost" type="button" :disabled="loading" @click="emit('cancel')">Cancelar</Button>
      <Button type="submit" :loading="loading">{{ submitLabel ?? 'Salvar' }}</Button>
    </div>
  </form>
</template>
