<script setup lang="ts">
import { useForm } from 'vee-validate'
import { watch } from 'vue'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import AppDialog from '@/components/ui/AppDialog.vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Textarea from '@/components/ui/Textarea.vue'
import { emptyToMoney, emptyToNull } from '@/lib/formatters'
import type { StockAdjustPayload } from '@/types/product'

const open = defineModel<boolean>('open', { default: false })

const props = defineProps<{
  loading?: boolean
}>()

const emit = defineEmits<{
  submit: [payload: StockAdjustPayload]
}>()

const schema = toTypedSchema(
  z
    .object({
      type: z.string().min(1, 'Selecione entrada ou saída.').refine((value) => value === 'in' || value === 'out', 'Selecione entrada ou saída.'),
      quantity: z
        .string()
        .trim()
        .min(1, 'Informe a quantidade.')
        .refine((value) => {
          const parsed = Number(emptyToMoney(value))
          return Number.isFinite(parsed) && parsed > 0
        }, 'Quantidade deve ser maior que zero.'),
      unit_cost: z.string(),
      reason: z.string(),
      notes: z.string(),
    })
    .superRefine((values, ctx) => {
      if (values.type === 'in') {
        const cost = emptyToMoney(values.unit_cost)
        if (cost === null || Number(cost) < 0) {
          ctx.addIssue({
            code: z.ZodIssueCode.custom,
            message: 'Informe o custo unitário da entrada.',
            path: ['unit_cost'],
          })
        }
      }
    }),
)

const { defineField, handleSubmit, errors, resetForm, setErrors } = useForm({
  validationSchema: schema,
  initialValues: {
    type: 'in',
    quantity: '',
    unit_cost: '',
    reason: '',
    notes: '',
  },
})

const [type] = defineField('type')
const [quantity, quantityAttrs] = defineField('quantity')
const [unitCost, unitCostAttrs] = defineField('unit_cost')
const [reason, reasonAttrs] = defineField('reason')
const [notes, notesAttrs] = defineField('notes')

watch(open, (isOpen) => {
  if (isOpen) {
    resetForm({
      values: {
        type: 'in',
        quantity: '',
        unit_cost: '',
        reason: '',
        notes: '',
      },
    })
  }
})

const typeOptions = [
  { value: 'in', label: 'Entrada' },
  { value: 'out', label: 'Saída' },
]

const onSubmit = handleSubmit((values) => {
  emit('submit', {
    type: values.type as 'in' | 'out',
    quantity: emptyToMoney(values.quantity) ?? values.quantity,
    unit_cost: values.type === 'in' ? emptyToMoney(values.unit_cost) : null,
    reason: emptyToNull(values.reason),
    notes: emptyToNull(values.notes),
  })
})

defineExpose({ setErrors })
</script>

<template>
  <AppDialog
    v-model:open="open"
    title="Ajustar estoque"
    description="Entrada recalcula o custo médio. Saída não altera o custo."
  >
    <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
      <FormField label="Movimento" :error="errors.type" html-for="stock-type">
        <template #default="{ invalid }">
          <Select id="stock-type" v-model="type" :options="typeOptions" :invalid="invalid" />
        </template>
      </FormField>
      <FormField label="Quantidade" :error="errors.quantity" html-for="stock-qty">
        <template #default="{ invalid }">
          <Input
            id="stock-qty"
            v-model="quantity"
            v-bind="quantityAttrs"
            type="text"
            inputmode="decimal"
            :invalid="invalid"
          />
        </template>
      </FormField>
      <FormField
        v-if="type === 'in'"
        label="Custo unitário"
        :error="errors.unit_cost"
        html-for="stock-cost"
      >
        <template #default="{ invalid }">
          <Input
            id="stock-cost"
            v-model="unitCost"
            v-bind="unitCostAttrs"
            type="text"
            inputmode="decimal"
            :invalid="invalid"
          />
        </template>
      </FormField>
      <FormField label="Motivo" :error="errors.reason" html-for="stock-reason">
        <Input id="stock-reason" v-model="reason" v-bind="reasonAttrs" type="text" />
      </FormField>
      <FormField label="Notas" :error="errors.notes" html-for="stock-notes">
        <Textarea id="stock-notes" v-model="notes" v-bind="notesAttrs" />
      </FormField>
    </form>
    <template #footer>
      <Button variant="ghost" type="button" @click="open = false">Cancelar</Button>
      <Button type="button" :loading="props.loading" @click="onSubmit">Confirmar</Button>
    </template>
  </AppDialog>
</template>
