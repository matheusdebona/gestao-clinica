<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, onUnmounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import ItemLineRow from '@/components/patterns/ItemLineRow.vue'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import ListCard from '@/components/ui/ListCard.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SearchField from '@/components/ui/SearchField.vue'
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import {
  completeAppointment,
  getAppointment,
  syncAppointmentConsumptions,
} from '@/features/appointments/api'
import { APPOINTMENT_STATUS_LABELS } from '@/features/appointments/labels'
import { listCardBrands, listCardOperators, listPaymentMethods } from '@/features/payments/api'
import { listProducts } from '@/features/products/api'
import ExtraChargeFields from '@/features/treatments/ExtraChargeFields.vue'
import { getTreatmentFulfillment } from '@/features/treatments/api'
import {
  buildConsumptionDrafts,
  draftsHaveProducts,
  extraKind,
  newExtraDraft,
  setExtraKind,
  stockWarningsFromAppointment,
  toConsumptionPayloads,
  validateConsumptionDrafts,
  type ConsumptionDraft,
} from '@/features/treatments/consumptions'
import { formatBRL, formatDateTime, formatQty } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { Product } from '@/types/product'
import type { ConsumptionPayload } from '@/types/appointment'
import { ApiError } from '@/types/user'

const props = defineProps<{
  appointmentId: number
}>()

const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()
const queryClient = useQueryClient()

const drafts = ref<ConsumptionDraft[]>([])
const hydrated = ref(false)
const formError = ref('')
const completeOpen = ref(false)
const emptyOpen = ref(false)
const productSearchInput = ref('')
const productQ = ref('')
let productTimer: ReturnType<typeof setTimeout> | undefined

const extraKindOptions = [
  { value: 'courtesy', label: 'Cortesia' },
  { value: 'charged', label: 'Cobrado' },
]

const idRef = computed(() => props.appointmentId)
const canConsume = computed(() => auth.can('treatments.consume'))
const canSearchProducts = computed(() => auth.can('products.view') || auth.can('treatments.consume'))

const { data: appointment, isPending, isError } = useQuery({
  queryKey: ['appointments', idRef],
  queryFn: () => getAppointment(idRef.value),
  enabled: computed(() => auth.can('appointments.view') || auth.can('treatments.view')),
})

const treatmentId = computed(() => appointment.value?.treatment_id)

const fulfillmentQuery = useQuery({
  queryKey: ['treatments', treatmentId, 'fulfillment'],
  queryFn: () => getTreatmentFulfillment(treatmentId.value as number),
  enabled: computed(() => Boolean(treatmentId.value)),
})

const paymentsQuery = useQuery({
  queryKey: ['payment-methods'],
  queryFn: listPaymentMethods,
  enabled: canConsume,
})
const operatorsQuery = useQuery({
  queryKey: ['card-operators'],
  queryFn: listCardOperators,
  enabled: canConsume,
})
const brandsQuery = useQuery({
  queryKey: ['card-brands'],
  queryFn: listCardBrands,
  enabled: canConsume,
})

watch(productSearchInput, (value) => {
  window.clearTimeout(productTimer)
  productTimer = window.setTimeout(() => {
    productQ.value = value.trim()
  }, 300)
})

onUnmounted(() => {
  window.clearTimeout(productTimer)
})

const productQuery = useQuery({
  queryKey: ['products', 'consume-pick', productQ],
  queryFn: () => listProducts({ q: productQ.value, page: 1, is_active: true }),
  enabled: computed(() => canSearchProducts.value && productQ.value.length > 0),
})

const productHits = computed(() => productQuery.data.value?.data ?? [])
const methods = computed(() => paymentsQuery.data.value ?? [])
const operators = computed(() => operatorsQuery.data.value ?? [])
const brands = computed(() => brandsQuery.data.value ?? [])
const isInProgress = computed(() => appointment.value?.status === 'in_progress')
const stockWarnings = computed(() => stockWarningsFromAppointment(appointment.value))
const suggestedLines = computed(() => drafts.value.filter((line) => line.source === 'suggested'))
const extraLines = computed(() => drafts.value.filter((line) => line.source === 'extra'))

watch(
  [appointment, fulfillmentQuery.data],
  ([nextAppointment, fulfillment]) => {
    if (hydrated.value || !nextAppointment || !fulfillment) {
      return
    }
    drafts.value = buildConsumptionDrafts(fulfillment.items, nextAppointment.consumptions ?? [])
    hydrated.value = true
  },
  { immediate: true },
)

function lineUnit(line: ConsumptionDraft) {
  const parts: string[] = []
  if (line.remaining_quantity) {
    parts.push(`Saldo ${formatQty(line.remaining_quantity)}`)
  }
  if (line.stock_quantity !== undefined && line.stock_quantity !== '') {
    parts.push(`Estoque ${formatQty(line.stock_quantity)}`)
  }
  return parts.join(' · ')
}

function onExtraKind(line: ConsumptionDraft, value: string) {
  const kind = value === 'charged' ? 'charged' : 'courtesy'
  const product = productHits.value.find((item) => item.id === line.product_id)
  setExtraKind(line, kind, product?.sale_price)
}

function addExtra(product: Product) {
  if (drafts.value.some((line) => line.source === 'extra' && line.product_id === product.id && extraKind(line) === 'courtesy')) {
    toast.info('Extra de cortesia já adicionado. Ajuste a quantidade.')
    return
  }
  drafts.value = [...drafts.value, newExtraDraft(product)]
  productSearchInput.value = ''
  productQ.value = ''
}

function removeLine(key: string) {
  drafts.value = drafts.value.filter((line) => line.key !== key)
}

async function persist(consumptions?: ConsumptionPayload[]) {
  if (consumptions === undefined) {
    const error = validateConsumptionDrafts(drafts.value)
    if (error) {
      formError.value = error
      throw new Error(error)
    }
    formError.value = ''
    return syncAppointmentConsumptions(props.appointmentId, toConsumptionPayloads(drafts.value))
  }
  formError.value = ''
  return syncAppointmentConsumptions(props.appointmentId, consumptions)
}

const saveMutation = useMutation({
  mutationFn: () => persist(),
  onSuccess: async () => {
    toast.success('Consumo salvo')
    await queryClient.invalidateQueries({ queryKey: ['appointments'] })
    await queryClient.invalidateQueries({ queryKey: ['treatments'] })
  },
  onError: (error) => {
    if (error instanceof ApiError) {
      toast.error(error.message)
      return
    }
    if (error instanceof Error && error.message) {
      toast.error(error.message)
      return
    }
    toast.error('Não foi possível salvar o consumo.')
  },
})

const completeMutation = useMutation({
  mutationFn: async (empty: boolean) => {
    if (empty) {
      await persist([])
    } else {
      await persist()
    }
    return completeAppointment(props.appointmentId)
  },
  onSuccess: async (result) => {
    toast.success(`Sessão concluída · custo ${formatBRL(result.total_cost)}`)
    await queryClient.invalidateQueries({ queryKey: ['appointments'] })
    await queryClient.invalidateQueries({ queryKey: ['treatments'] })
    await router.push({ name: 'appointments-show', params: { id: String(props.appointmentId) } })
  },
  onError: (error) => {
    if (error instanceof ApiError) {
      toast.error(error.message)
      return
    }
    if (error instanceof Error && error.message) {
      toast.error(error.message)
      return
    }
    toast.error('Não foi possível concluir a sessão.')
  },
})

function goBack() {
  void router.push({ name: 'appointments-show', params: { id: String(props.appointmentId) } })
}

const saving = computed(() => saveMutation.isPending.value || completeMutation.isPending.value)
const savePending = computed(() => saveMutation.isPending.value)
const completePending = computed(() => completeMutation.isPending.value)
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="appointment?.client?.name ?? 'Consumo'">
      <template #actions>
        <Button variant="ghost" @click="goBack">Voltar</Button>
      </template>
    </PageHeader>

    <Banner v-if="!canConsume" variant="danger" title="Sem permissão">
      Você não pode registrar o consumo desta sessão.
    </Banner>

    <Banner v-else-if="isError" variant="danger" title="Não foi possível carregar">
      A sessão pode ter sido removida ou você não tem permissão.
    </Banner>

    <SurfaceCard v-else-if="isPending || fulfillmentQuery.isPending">
      <Skeleton class="h-6 w-40" />
      <Skeleton class="mt-3 h-5 w-56" />
    </SurfaceCard>

    <template v-else-if="appointment">
      <Banner v-if="!isInProgress" variant="warning" title="Sessão não está em atendimento">
        Só dá para registrar consumo com a sessão em andamento. Situação atual:
        {{ APPOINTMENT_STATUS_LABELS[appointment.status] }}.
      </Banner>
      <Banner
        v-for="warning in stockWarnings"
        :key="warning.product_id"
        variant="warning"
        :title="warning.product_name"
      >
        Estoque {{ warning.stock_quantity }} para sugerido {{ warning.suggested_quantity }}. O aviso não bloqueia a
        conclusão.
      </Banner>

      <SurfaceCard>
        <dl class="flex flex-col gap-4">
          <div>
            <dt class="text-[13px] text-muted">Cliente</dt>
            <dd class="mt-0.5 text-[15px] text-title">{{ appointment.client?.name ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Data e hora</dt>
            <dd class="mt-0.5 text-[15px] text-title">{{ formatDateTime(appointment.scheduled_at) }}</dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Profissional</dt>
            <dd class="mt-0.5 text-[15px] text-title">{{ appointment.professional?.name ?? '—' }}</dd>
          </div>
        </dl>
      </SurfaceCard>

      <template v-if="isInProgress">
        <div>
          <h2 class="mb-3">Produtos da venda</h2>
          <p class="mb-3 text-[13px] text-muted">
            Quantidades sugeridas pelo saldo restante. Ajuste ou zere antes de concluir.
          </p>
          <SurfaceCard v-if="suggestedLines.length === 0" :padding="false">
            <p class="px-5 py-4 text-[15px] text-muted">Nenhum saldo restante da venda.</p>
          </SurfaceCard>
          <SurfaceCard v-else :padding="false">
            <div class="divide-y divide-border-divider px-5 py-2">
              <ItemLineRow
                v-for="line in suggestedLines"
                :key="line.key"
                v-model:quantity="line.quantity"
                :title="line.product_name"
                :unit="lineUnit(line)"
                @remove="removeLine(line.key)"
              />
            </div>
          </SurfaceCard>
        </div>

        <div>
          <h2 class="mb-3">Extras</h2>
          <p class="mb-3 text-[13px] text-muted">Cortesia ou cobrado na hora (pagamento na venda).</p>
          <SurfaceCard v-if="extraLines.length > 0" :padding="false">
            <div class="divide-y divide-border-divider px-5 py-4">
              <div v-for="line in extraLines" :key="line.key" class="py-3 first:pt-0 last:pb-0">
                <ItemLineRow
                  v-model:quantity="line.quantity"
                  :title="line.product_name"
                  :unit="lineUnit(line)"
                  @remove="removeLine(line.key)"
                />
                <FormField label="Tipo" class="mt-2">
                  <Select
                    :model-value="extraKind(line)"
                    :options="extraKindOptions"
                    @update:model-value="onExtraKind(line, $event)"
                  />
                </FormField>
                <ExtraChargeFields
                  v-if="extraKind(line) === 'charged'"
                  :line="line"
                  :methods="methods"
                  :operators="operators"
                  :brands="brands"
                />
              </div>
            </div>
          </SurfaceCard>

          <div v-if="canSearchProducts" class="mt-4 flex flex-col gap-3">
            <FormField label="Adicionar extra" hint="Busque um produto da clínica.">
              <SearchField
                v-model="productSearchInput"
                placeholder="Nome do produto"
              />
            </FormField>
            <SurfaceCard v-if="productQuery.isFetching && productQ" :padding="false">
              <div class="p-5">
                <Skeleton class="h-10" />
              </div>
            </SurfaceCard>
            <SurfaceCard v-else-if="productHits.length > 0" :padding="false">
              <div class="divide-y divide-border-divider px-5 py-2">
                <ListCard
                  v-for="product in productHits"
                  :key="product.id"
                  :title="product.name"
                  :meta="`Estoque ${formatQty(product.stock_quantity)}`"
                  @action="addExtra(product)"
                />
              </div>
            </SurfaceCard>
          </div>
        </div>

        <InlineAlert v-if="formError">{{ formError }}</InlineAlert>

        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
          <Button variant="secondary" :loading="savePending" :disabled="saving" @click="saveMutation.mutate()">
            Salvar consumo
          </Button>
          <PermissionGate permission="treatments.complete">
            <Button :loading="completePending" :disabled="saving" @click="completeOpen = true">
              Concluir sessão
            </Button>
          </PermissionGate>
          <PermissionGate permission="treatments.complete">
            <Button variant="ghost" :disabled="saving" @click="emptyOpen = true">
              Concluir sem produtos
            </Button>
          </PermissionGate>
        </div>
      </template>
    </template>

    <ConfirmDialog
      v-model:open="completeOpen"
      title="Concluir esta sessão?"
      description="O estoque desce pelo consumo informado. Estoque negativo não bloqueia."
      confirm-label="Concluir sessão"
      confirm-variant="primary"
      @confirm="completeMutation.mutate(false)"
    />
    <ConfirmDialog
      v-model:open="emptyOpen"
      title="Concluir sem produtos?"
      :description="
        draftsHaveProducts(drafts)
          ? 'As quantidades preenchidas serão ignoradas. Nenhum produto sai do estoque.'
          : 'Sessão de avaliação: nenhum produto sai do estoque.'
      "
      confirm-label="Concluir sem produtos"
      confirm-variant="primary"
      @confirm="completeMutation.mutate(true)"
    />
  </div>
</template>
