<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import ClientSearchBar from '@/components/patterns/ClientSearchBar.vue'
import MoneyDisplay from '@/components/patterns/MoneyDisplay.vue'
import WizardStepper from '@/components/patterns/WizardStepper.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import Input from '@/components/ui/Input.vue'
import ListCard from '@/components/ui/ListCard.vue'
import MaskedBox from '@/components/ui/MaskedBox.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Textarea from '@/components/ui/Textarea.vue'
import { listClients } from '@/features/clients/api'
import { listCardBrands, listCardOperators, listPaymentMethods } from '@/features/payments/api'
import { applyProtocolToSale, createSale, getSale, syncSaleItems, syncSalePayments, updateSale, confirmSale } from '@/features/sales/api'
import { SALE_WIZARD_STEPS } from '@/features/sales/labels'
import SaleBudgetsPanel from '@/features/sales/SaleBudgetsPanel.vue'
import SaleItemsStep from '@/features/sales/SaleItemsStep.vue'
import SalePaymentsStep from '@/features/sales/SalePaymentsStep.vue'
import {
  draftsToItemPayloads,
  draftsToPaymentPayloads,
  emptyPaymentDraft,
  expectedFromDrafts,
  money2,
  parseMoneyAmount,
  parseQuantity,
  paymentsSum,
  paymentsToDrafts,
  remainingBalance,
  saleItemsToDrafts,
  type SaleItemDraft,
  type SalePaymentDraft,
} from '@/features/sales/schema'
import { formatBRL } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { Client } from '@/types/client'
import type { Sale } from '@/types/sale'
import { ApiError } from '@/types/user'

const props = defineProps<{
  saleId?: number
}>()

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const step = ref(props.saleId ? 1 : 0)
const saving = ref(false)
const clientSearch = ref('')
const clientQ = ref('')
const selectedClient = ref<Client | null>(null)
const notes = ref('')
const items = ref<SaleItemDraft[]>([])
const itemsError = ref('')
const effectiveAmount = ref('')
const effectiveDirty = ref(false)
const payments = ref<SalePaymentDraft[]>([])
const paymentsError = ref('')
const belowMinOpen = ref(false)
const currentSale = ref<Sale | null>(null)

const canCreate = computed(() => auth.can('sales.create'))
const canUpdate = computed(() => auth.can('sales.update'))
const allowed = computed(() => (props.saleId ? canUpdate.value : canCreate.value))

const {
  data: loadedSale,
  isPending: salePending,
  isError: saleError,
} = useQuery({
  queryKey: ['sales', computed(() => props.saleId)],
  queryFn: () => getSale(props.saleId as number),
  enabled: computed(() => Boolean(props.saleId) && allowed.value),
})

watch(
  loadedSale,
  (sale) => {
    if (!sale) {
      return
    }
    if (sale.status !== 'draft') {
      void router.replace({ name: 'sales-show', params: { id: String(sale.id) } })
      return
    }
    hydrate(sale)
  },
  { immediate: true },
)

const clientQuery = useQuery({
  queryKey: ['clients', 'sale-pick', clientQ],
  queryFn: () => listClients({ q: clientQ.value, page: 1, is_active: true }),
  enabled: computed(() => step.value === 0 && auth.can('clients.view') && clientQ.value.length > 0),
})

const clients = computed(() => clientQuery.data.value?.data ?? [])

const catalogsEnabled = computed(() => Boolean(props.saleId || currentSale.value) && step.value >= 2)

const methodsQuery = useQuery({
  queryKey: ['payment-methods'],
  queryFn: listPaymentMethods,
  enabled: catalogsEnabled,
})
const operatorsQuery = useQuery({
  queryKey: ['card-operators'],
  queryFn: listCardOperators,
  enabled: catalogsEnabled,
})
const brandsQuery = useQuery({
  queryKey: ['card-brands'],
  queryFn: listCardBrands,
  enabled: catalogsEnabled,
})

const paymentMethods = computed(() => methodsQuery.data.value ?? [])
const cardOperators = computed(() => operatorsQuery.data.value ?? [])
const cardBrands = computed(() => brandsQuery.data.value ?? [])

const expectedLocal = computed(() => money2(expectedFromDrafts(items.value)))
const minLocal = computed(() => {
  return money2(
    items.value.reduce((sum, item) => {
      const qty = parseQuantity(item.quantity)
      const min = Number(item.min_unit_price ?? 0)
      if (!Number.isFinite(qty) || qty <= 0) {
        return sum
      }
      return sum + qty * (Number.isFinite(min) ? min : 0)
    }, 0),
  )
})
const belowMinimum = computed(() => {
  const effective = parseMoneyAmount(effectiveAmount.value || expectedLocal.value)
  return Number.isFinite(effective) && effective < Number(minLocal.value)
})

function hydrate(sale: Sale) {
  currentSale.value = sale
  selectedClient.value = sale.client ?? selectedClient.value
  notes.value = sale.notes ?? ''
  items.value = saleItemsToDrafts(sale.items)
  effectiveAmount.value = sale.effective_amount
  effectiveDirty.value = sale.effective_amount_is_manual
  payments.value = paymentsToDrafts(sale.payments)
  if (payments.value.length === 0 && Number(sale.effective_amount) > 0) {
    payments.value = [emptyPaymentDraft(sale.effective_amount)]
  }
}

function apiError(error: unknown, fallback: string) {
  if (error instanceof ApiError && error.status === 403) {
    toast.error('Sem permissão.')
    return
  }
  toast.error(error instanceof ApiError ? error.message : fallback)
}

async function persistItems(saleId: number) {
  return syncSaleItems(saleId, draftsToItemPayloads(items.value))
}

async function persistValues(saleId: number) {
  const payload: { notes: string | null; effective_amount?: string } = {
    notes: notes.value.trim() || null,
  }
  if (effectiveDirty.value) {
    payload.effective_amount = emptyEffective()
  }
  return updateSale(saleId, payload)
}

function emptyEffective() {
  const parsed = parseMoneyAmount(effectiveAmount.value)
  if (!Number.isFinite(parsed) || parsed < 0) {
    return expectedLocal.value
  }
  return money2(parsed)
}

async function persistPayments(saleId: number) {
  return syncSalePayments(saleId, draftsToPaymentPayloads(payments.value))
}

function validateItems(): boolean {
  if (items.value.length === 0) {
    itemsError.value = 'Inclua pelo menos um produto.'
    return false
  }
  const invalid = items.value.some(
    (item) => !(parseQuantity(item.quantity) > 0) || !(parseMoneyAmount(item.unit_price) >= 0),
  )
  if (invalid) {
    itemsError.value = 'Informe quantidade maior que zero e um preço válido.'
    return false
  }
  itemsError.value = ''
  return true
}

function validatePayments(): boolean {
  if (payments.value.length === 0) {
    paymentsError.value = 'Inclua ao menos um pagamento.'
    return false
  }
  for (const payment of payments.value) {
    if (!payment.payment_method_id) {
      paymentsError.value = 'Selecione o método de cada pagamento.'
      return false
    }
    if (!(parseMoneyAmount(payment.amount) > 0)) {
      paymentsError.value = 'Informe um valor maior que zero em cada pagamento.'
      return false
    }
    const method = methodsQuery.data.value?.find(
      (entry) => String(entry.id) === payment.payment_method_id,
    )
    if (method?.requires_card_meta) {
      if (!payment.card_operator_id || !payment.card_brand_id || !payment.installments) {
        paymentsError.value = 'Cartão exige operadora, bandeira e parcelas.'
        return false
      }
    }
  }
  const effective = Number(emptyEffective())
  if (Math.abs(paymentsSum(payments.value) - effective) > 0.001) {
    paymentsError.value = `Os pagamentos devem fechar ${formatBRL(effective)}. Saldo: ${formatBRL(remainingBalance(effective, payments.value))}.`
    return false
  }
  paymentsError.value = ''
  return true
}

async function goNext() {
  if (step.value === 0) {
    if (!selectedClient.value) {
      toast.error('Selecione o cliente.')
      return
    }
    saving.value = true
    try {
      if (!currentSale.value) {
        const created = await createSale({
          client_id: selectedClient.value.id,
          notes: notes.value.trim() || null,
        })
        toast.success('Venda criada')
        if (!auth.can('sales.update')) {
          await router.replace({
            name: 'sales-show',
            params: { id: String(created.id) },
          })
          return
        }
        await router.replace({
          name: 'sales-edit',
          params: { id: String(created.id) },
        })
        return
      }
      step.value = 1
    } catch (error) {
      apiError(error, 'Não foi possível criar a venda.')
    } finally {
      saving.value = false
    }
    return
  }

  const saleId = currentSale.value?.id ?? props.saleId
  if (!saleId) {
    return
  }

  if (step.value === 1) {
    if (!validateItems()) {
      return
    }
    saving.value = true
    try {
      hydrate(await persistItems(saleId))
      if (!effectiveDirty.value) {
        effectiveAmount.value = currentSale.value?.effective_amount ?? expectedLocal.value
      }
      step.value = 2
    } catch (error) {
      apiError(error, 'Não foi possível salvar os itens.')
    } finally {
      saving.value = false
    }
    return
  }

  if (step.value === 2) {
    saving.value = true
    try {
      hydrate(await persistValues(saleId))
      if (payments.value.length === 0) {
        payments.value = [emptyPaymentDraft(emptyEffective())]
      }
      step.value = 3
    } catch (error) {
      apiError(error, 'Não foi possível salvar o valor efetivo.')
    } finally {
      saving.value = false
    }
    return
  }

  if (step.value === 3) {
    if (!validatePayments()) {
      return
    }
    saving.value = true
    try {
      hydrate(await persistPayments(saleId))
      step.value = 4
    } catch (error) {
      apiError(error, 'Não foi possível salvar os pagamentos.')
    } finally {
      saving.value = false
    }
  }
}

function goBack() {
  if (step.value === 0) {
    onCancel()
    return
  }
  step.value -= 1
}

function onCancel() {
  if (currentSale.value?.id || props.saleId) {
    void router.push({
      name: 'sales-show',
      params: { id: String(currentSale.value?.id ?? props.saleId) },
    })
    return
  }
  void router.push({ name: 'sales' })
}

async function onApplyProtocol(protocolId: number) {
  const saleId = currentSale.value?.id ?? props.saleId
  if (!saleId) {
    toast.error('Salve o cliente antes de adicionar protocolo.')
    return
  }
  saving.value = true
  try {
    if (items.value.length > 0) {
      if (!validateItems()) {
        return
      }
      await persistItems(saleId)
    }
    hydrate(await applyProtocolToSale(saleId, protocolId))
    toast.success('Protocolo adicionado')
  } catch (error) {
    apiError(error, 'Não foi possível adicionar o protocolo.')
  } finally {
    saving.value = false
  }
}

async function doConfirm(belowMin: boolean) {
  const saleId = currentSale.value?.id ?? props.saleId
  if (!saleId) {
    return
  }
  saving.value = true
  try {
    if (!validatePayments()) {
      step.value = 3
      return
    }
    await persistPayments(saleId)
    const confirmed = await confirmSale(saleId, belowMin)
    toast.success('Venda confirmada')
    await router.push({ name: 'sales-show', params: { id: String(confirmed.id) } })
  } catch (error) {
    if (error instanceof ApiError && error.errors.confirm_below_minimum) {
      belowMinOpen.value = true
      return
    }
    apiError(error, 'Não foi possível confirmar a venda.')
  } finally {
    saving.value = false
  }
}

function onConfirmClick() {
  if (belowMinimum.value) {
    belowMinOpen.value = true
    return
  }
  void doConfirm(false)
}

function methodName(id: string) {
  return methodsQuery.data.value?.find((method) => String(method.id) === id)?.name ?? 'Pagamento'
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <WizardStepper :steps="[...SALE_WIZARD_STEPS]" :current="step" @select="step = $event" />

    <Banner v-if="!allowed" variant="danger" title="Sem permissão">
      Você não pode {{ saleId ? 'editar' : 'criar' }} vendas.
    </Banner>
    <Banner v-else-if="saleId && saleError" variant="danger" title="Não encontrado">
      Esta venda não está disponível.
    </Banner>
    <SurfaceCard v-else-if="saleId && salePending">
      <Skeleton class="h-11" />
      <Skeleton class="mt-3 h-24" />
    </SurfaceCard>

    <template v-else>
      <div v-if="step === 0" class="flex flex-col gap-4">
        <Banner v-if="currentSale" variant="info" title="Cliente definido">
          O cliente não muda depois que a venda é criada.
        </Banner>
        <p v-if="selectedClient" class="text-[15px] font-medium text-title">
          {{ selectedClient.name }}
          <span class="mt-0.5 block text-[13px] font-normal text-muted">{{ selectedClient.whatsapp }}</span>
        </p>
        <template v-if="!currentSale">
          <ClientSearchBar v-model="clientSearch" @search="clientQ = $event" />
          <SurfaceCard v-if="clientQ && clientQuery.isPending" :padding="false">
            <div class="p-5"><Skeleton class="h-12" /></div>
          </SurfaceCard>
          <SurfaceCard v-else-if="clientQ && clients.length === 0" :padding="false">
            <p class="px-5 py-4 text-[15px] text-muted">Nenhum cliente encontrado.</p>
          </SurfaceCard>
          <SurfaceCard v-else-if="clients.length > 0" :padding="false">
            <div class="divide-y divide-border-divider px-5 py-2">
              <ListCard
                v-for="client in clients"
                :key="client.id"
                :title="client.name"
                :meta="client.whatsapp"
                @action="selectedClient = client"
              />
            </div>
          </SurfaceCard>
        </template>
        <FormField label="Notas" html-for="sale-notes">
          <Textarea id="sale-notes" v-model="notes" />
        </FormField>
      </div>

      <SaleItemsStep
        v-else-if="step === 1"
        v-model:items="items"
        :protocol-references="currentSale?.protocol_references"
        :error="itemsError"
        @apply-protocol="onApplyProtocol"
      />

      <div v-else-if="step === 2" class="flex flex-col gap-4">
        <FormField label="Valor esperado" hint="Soma das linhas. Somente leitura.">
          <MaskedBox :value="formatBRL(expectedLocal)" />
        </FormField>
        <FormField label="Mínimo" hint="Piso das linhas. Somente leitura.">
          <MaskedBox :value="formatBRL(minLocal)" />
        </FormField>
        <FormField
          label="Valor efetivo"
          hint="Valor cobrado. Pode editar."
          html-for="sale-effective"
        >
          <Input
            id="sale-effective"
            v-model="effectiveAmount"
            type="text"
            inputmode="decimal"
            @update:model-value="effectiveDirty = true"
          />
        </FormField>
        <Banner v-if="belowMinimum" variant="warning" title="Abaixo do mínimo">
          Confirmar esta venda vai pedir uma confirmação extra.
        </Banner>
      </div>

      <SalePaymentsStep
        v-else-if="step === 3"
        v-model:payments="payments"
        :effective-amount="emptyEffective()"
        :methods="paymentMethods"
        :operators="cardOperators"
        :brands="cardBrands"
        :error="paymentsError"
      />

      <div v-else class="flex flex-col gap-4">
        <SurfaceCard>
          <dl class="flex flex-col gap-4">
            <div>
              <dt class="text-[13px] text-muted">Cliente</dt>
              <dd class="mt-0.5 text-[15px] text-title">
                {{ selectedClient?.name ?? currentSale?.client?.name }}
              </dd>
            </div>
            <div>
              <dt class="text-[13px] text-muted">Itens</dt>
              <dd class="mt-0.5 text-[15px] text-title">{{ items.length }}</dd>
            </div>
            <div>
              <dt class="text-[13px] text-muted">Valor efetivo</dt>
              <dd class="mt-0.5"><MoneyDisplay :value="emptyEffective()" /></dd>
            </div>
            <div>
              <dt class="text-[13px] text-muted">Pagamentos</dt>
              <dd class="mt-0.5 text-[15px] text-title">
                <p v-for="payment in payments" :key="payment.key">
                  {{ methodName(payment.payment_method_id) }} · {{ formatBRL(payment.amount) }}
                </p>
              </dd>
            </div>
          </dl>
        </SurfaceCard>
        <InlineAlert v-if="belowMinimum" variant="warning">
          O valor efetivo está abaixo do mínimo {{ formatBRL(minLocal) }}.
        </InlineAlert>
        <PermissionGate permission="sales.confirm">
          <Button :loading="saving" @click="onConfirmClick">Confirmar venda</Button>
        </PermissionGate>
        <SaleBudgetsPanel
          v-if="currentSale?.id || saleId"
          :sale-id="(currentSale?.id ?? saleId) as number"
          :can-create="items.length > 0"
        />
      </div>

      <div class="mt-2 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button variant="ghost" type="button" :disabled="saving" @click="goBack">
          {{ step === 0 ? 'Cancelar' : 'Voltar' }}
        </Button>
        <Button v-if="step < 4" :loading="saving" @click="goNext">
          Continuar
        </Button>
      </div>
    </template>

    <ConfirmDialog
      v-model:open="belowMinOpen"
      title="Confirmar abaixo do mínimo?"
      :description="`O valor efetivo ${formatBRL(emptyEffective())} ficou abaixo de ${formatBRL(minLocal)}. Continuar mesmo assim?`"
      confirm-label="Confirmar mesmo assim"
      confirm-variant="primary"
      @confirm="doConfirm(true)"
    />
  </div>
</template>
