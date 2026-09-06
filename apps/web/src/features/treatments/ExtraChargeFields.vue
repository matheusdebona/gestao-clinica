<script setup lang="ts">
import { computed } from 'vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import type { ConsumptionDraft } from '@/features/treatments/consumptions'
import type { CardBrand, CardOperator, PaymentMethod } from '@/types/sale'

const props = defineProps<{
  line: ConsumptionDraft
  methods: PaymentMethod[]
  operators: CardOperator[]
  brands: CardBrand[]
}>()

const methodOptions = computed(() =>
  props.methods.map((method) => ({
    value: String(method.id),
    label: method.name,
  })),
)
const operatorOptions = computed(() =>
  props.operators.map((operator) => ({
    value: String(operator.id),
    label: operator.name,
  })),
)
const brandOptions = computed(() =>
  props.brands.map((brand) => ({
    value: String(brand.id),
    label: brand.name,
  })),
)
const installmentOptions = Array.from({ length: 12 }, (_, index) => ({
  value: String(index + 1),
  label: index === 0 ? '1x' : `${index + 1}x`,
}))

function methodFor(id: string) {
  return props.methods.find((method) => String(method.id) === id)
}
</script>

<template>
  <div class="mt-3 flex flex-col gap-3">
    <FormField label="Valor cobrado" :html-for="`extra-amount-${line.key}`">
      <Input
        :id="`extra-amount-${line.key}`"
        v-model="line.charged_amount"
        type="text"
        inputmode="decimal"
      />
    </FormField>
    <FormField label="Método" :html-for="`extra-method-${line.key}`">
      <Select
        :id="`extra-method-${line.key}`"
        v-model="line.payment_method_id"
        :options="methodOptions"
        placeholder="Selecionar método"
      />
    </FormField>
    <template v-if="methodFor(line.payment_method_id)?.requires_card_meta">
      <FormField label="Operadora">
        <Select
          v-model="line.card_operator_id"
          :options="operatorOptions"
          placeholder="Selecionar operadora"
        />
      </FormField>
      <FormField label="Bandeira">
        <Select
          v-model="line.card_brand_id"
          :options="brandOptions"
          placeholder="Selecionar bandeira"
        />
      </FormField>
      <FormField label="Parcelas">
        <Select
          v-model="line.installments"
          :options="installmentOptions"
          placeholder="Parcelas"
        />
      </FormField>
    </template>
  </div>
</template>
