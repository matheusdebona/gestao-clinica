<script setup lang="ts">
import { computed } from 'vue'
import MoneyDisplay from '@/components/patterns/MoneyDisplay.vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import IconButton from '@/components/ui/IconButton.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { X } from '@lucide/vue'
import {
  emptyPaymentDraft,
  paymentsSum,
  remainingBalance,
  type SalePaymentDraft,
} from '@/features/sales/schema'
import { formatBRL } from '@/lib/formatters'
import type { CardBrand, CardOperator, PaymentMethod } from '@/types/sale'

const payments = defineModel<SalePaymentDraft[]>('payments', { required: true })

const props = defineProps<{
  effectiveAmount: string
  methods: PaymentMethod[]
  operators: CardOperator[]
  brands: CardBrand[]
  error?: string
}>()

const methodOptions = computed(() =>
  props.methods.map((method) => ({ value: String(method.id), label: method.name })),
)
const operatorOptions = computed(() =>
  props.operators.map((operator) => ({ value: String(operator.id), label: operator.name })),
)
const brandOptions = computed(() =>
  props.brands.map((brand) => ({ value: String(brand.id), label: brand.name })),
)
const installmentOptions = Array.from({ length: 12 }, (_, index) => ({
  value: String(index + 1),
  label: index === 0 ? '1x' : `${index + 1}x`,
}))

const remaining = computed(() => remainingBalance(props.effectiveAmount, payments.value))
const paid = computed(() => paymentsSum(payments.value))

function methodFor(payment: SalePaymentDraft) {
  return props.methods.find((method) => String(method.id) === payment.payment_method_id)
}

function addPayment() {
  const leftover = remaining.value > 0 ? remaining.value.toFixed(2) : ''
  payments.value = [...payments.value, emptyPaymentDraft(leftover)]
}

function removePayment(key: string) {
  payments.value = payments.value.filter((payment) => payment.key !== key)
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <p class="text-[13px] text-muted">
      A soma dos pagamentos precisa fechar o valor efetivo. Sem pagamento parcial nesta etapa.
    </p>

    <SurfaceCard>
      <dl class="flex flex-col gap-3">
        <div class="flex items-center justify-between gap-3">
          <dt class="text-[13px] text-muted">Valor efetivo</dt>
          <dd><MoneyDisplay :value="effectiveAmount" /></dd>
        </div>
        <div class="flex items-center justify-between gap-3">
          <dt class="text-[13px] text-muted">Pago</dt>
          <dd><MoneyDisplay :value="paid" /></dd>
        </div>
        <div class="flex items-center justify-between gap-3">
          <dt class="text-[13px] text-muted">Saldo</dt>
          <dd><MoneyDisplay :value="remaining" /></dd>
        </div>
      </dl>
    </SurfaceCard>

    <InlineAlert v-if="error">{{ error }}</InlineAlert>
    <InlineAlert v-else-if="Math.abs(remaining) > 0.001" variant="warning">
      Falta {{ formatBRL(remaining) }} para fechar o total.
    </InlineAlert>
    <InlineAlert v-else-if="payments.length > 0" variant="info">
      Total fechado.
    </InlineAlert>

    <SurfaceCard v-for="payment in payments" :key="payment.key">
      <div class="flex items-start justify-between gap-2">
        <p class="text-[15px] font-medium text-title">Pagamento</p>
        <IconButton label="Remover pagamento" @click="removePayment(payment.key)">
          <X class="size-4" :stroke-width="1.75" />
        </IconButton>
      </div>
      <div class="mt-3 flex flex-col gap-3">
        <FormField label="Método" :html-for="`pay-method-${payment.key}`">
          <Select
            :id="`pay-method-${payment.key}`"
            v-model="payment.payment_method_id"
            :options="methodOptions"
            placeholder="Selecionar método"
          />
        </FormField>
        <FormField label="Valor" :html-for="`pay-amount-${payment.key}`">
          <Input
            :id="`pay-amount-${payment.key}`"
            v-model="payment.amount"
            type="text"
            inputmode="decimal"
          />
        </FormField>
        <template v-if="methodFor(payment)?.requires_card_meta">
          <FormField label="Operadora">
            <Select
              v-model="payment.card_operator_id"
              :options="operatorOptions"
              placeholder="Selecionar operadora"
            />
          </FormField>
          <FormField label="Bandeira">
            <Select
              v-model="payment.card_brand_id"
              :options="brandOptions"
              placeholder="Selecionar bandeira"
            />
          </FormField>
          <FormField label="Parcelas">
            <Select
              v-model="payment.installments"
              :options="installmentOptions"
              placeholder="Parcelas"
            />
          </FormField>
        </template>
      </div>
    </SurfaceCard>

    <Button variant="secondary" type="button" @click="addPayment">Adicionar pagamento</Button>
  </div>
</template>
