<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import ItemLineRow from '@/components/patterns/ItemLineRow.vue'
import MoneyDisplay from '@/components/patterns/MoneyDisplay.vue'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { cancelSale, getSale } from '@/features/sales/api'
import { SALE_STATUS_LABELS } from '@/features/sales/labels'
import SaleBudgetsPanel from '@/features/sales/SaleBudgetsPanel.vue'
import { qtyInput } from '@/features/sales/schema'
import { startTreatment } from '@/features/treatments/api'
import { formatDateTime, formatQty } from '@/lib/formatters'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'

const props = defineProps<{
  saleId: number
}>()

const router = useRouter()
const toast = useToastStore()
const queryClient = useQueryClient()
const cancelOpen = ref(false)

const idRef = computed(() => props.saleId)

const { data: sale, isPending, isError } = useQuery({
  queryKey: ['sales', idRef],
  queryFn: () => getSale(idRef.value),
})

const items = computed(() => sale.value?.items ?? [])
const payments = computed(() => sale.value?.payments ?? [])
const isDraft = computed(() => sale.value?.status === 'draft')
const isConfirmed = computed(() => sale.value?.status === 'confirmed')
const isCancelled = computed(() => sale.value?.status === 'cancelled')

const cancelMutation = useMutation({
  mutationFn: () => cancelSale(props.saleId),
  onSuccess: async () => {
    toast.success('Venda cancelada')
    await queryClient.invalidateQueries({ queryKey: ['sales'] })
  },
  onError: (error) => {
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível cancelar.')
  },
})

const treatmentMutation = useMutation({
  mutationFn: () => startTreatment(props.saleId),
  onSuccess: async () => {
    toast.success('Tratamento aberto')
    await queryClient.invalidateQueries({ queryKey: ['sales'] })
  },
  onError: (error) => {
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível abrir o tratamento.')
  },
})

const startingTreatment = computed(() => treatmentMutation.isPending.value)

function goEdit() {
  void router.push({ name: 'sales-edit', params: { id: String(props.saleId) } })
}

function goBack() {
  void router.push({ name: 'sales' })
}

function itemUnit(item: { product?: { unit_of_measure?: { name: string; symbol: string } | null } | null }) {
  const unit = item.product?.unit_of_measure
  return unit ? `${unit.name} (${unit.symbol})` : ''
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="sale?.client?.name ?? 'Venda'">
      <template #actions>
        <Button variant="ghost" @click="goBack">Voltar</Button>
        <PermissionGate v-if="isDraft" permission="sales.update">
          <Button variant="secondary" @click="goEdit">Continuar</Button>
        </PermissionGate>
        <PermissionGate v-if="isDraft || isConfirmed" permission="sales.cancel">
          <Button variant="destructive" @click="cancelOpen = true">Cancelar venda</Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="isError" variant="danger" title="Não foi possível carregar">
      A venda pode ter sido removida ou você não tem permissão.
    </Banner>

    <SurfaceCard v-else-if="isPending">
      <div class="flex flex-col gap-3">
        <Skeleton class="h-6 w-40" />
        <Skeleton class="h-5 w-56" />
      </div>
    </SurfaceCard>

    <template v-else-if="sale">
      <Banner v-if="isCancelled" variant="warning" title="Cancelada">
        Esta venda foi cancelada. O histórico permanece.
      </Banner>
      <Banner v-else-if="isConfirmed" variant="success" title="Confirmada">
        Confirmada sem baixa de estoque. Agenda e consumo ficam na etapa de tratamento.
      </Banner>
      <Banner v-else-if="isDraft" variant="info" title="Rascunho">
        Continue o assistente para itens, pagamentos, orçamento ou confirmação.
      </Banner>

      <SurfaceCard>
        <dl class="flex flex-col gap-4">
          <div>
            <dt class="text-[13px] text-muted">Status</dt>
            <dd class="mt-0.5 text-[15px] text-title">{{ SALE_STATUS_LABELS[sale.status] }}</dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Cliente</dt>
            <dd class="mt-0.5 text-[15px] text-title">{{ sale.client?.name }}</dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Data</dt>
            <dd class="mt-0.5 text-[15px] text-title">{{ formatDateTime(sale.sold_at ?? sale.created_at) }}</dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Valor esperado</dt>
            <dd class="mt-0.5"><MoneyDisplay :value="sale.expected_amount" /></dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Valor efetivo</dt>
            <dd class="mt-0.5"><MoneyDisplay :value="sale.effective_amount" /></dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Mínimo</dt>
            <dd class="mt-0.5"><MoneyDisplay :value="sale.min_amount" /></dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Notas</dt>
            <dd class="mt-0.5 whitespace-pre-wrap text-[15px] text-title">{{ sale.notes || '—' }}</dd>
          </div>
        </dl>
      </SurfaceCard>

      <div>
        <h2 class="mb-3">Itens</h2>
        <SurfaceCard v-if="items.length === 0" :padding="false">
          <p class="px-5 py-4 text-[15px] text-muted">Nenhum item nesta venda.</p>
        </SurfaceCard>
        <SurfaceCard v-else :padding="false">
          <div class="divide-y divide-border-divider px-5 py-2">
            <ItemLineRow
              v-for="item in items"
              :key="item.id"
              :title="item.product_name"
              :unit="itemUnit(item)"
              :line-sale="item.line_total"
              :quantity="formatQty(item.quantity)"
              :unit-price="qtyInput(item.unit_price)"
              show-unit-price
              readonly
            />
          </div>
        </SurfaceCard>
      </div>

      <div>
        <h2 class="mb-3">Pagamentos</h2>
        <SurfaceCard v-if="payments.length === 0" :padding="false">
          <p class="px-5 py-4 text-[15px] text-muted">Nenhum pagamento lançado.</p>
        </SurfaceCard>
        <SurfaceCard v-else>
          <ul class="flex flex-col gap-3">
            <li v-for="payment in payments" :key="payment.id" class="flex items-center justify-between gap-3">
              <span class="text-[15px] text-title">
                {{ payment.payment_method?.name ?? 'Pagamento' }}
              </span>
              <MoneyDisplay :value="payment.amount" />
            </li>
          </ul>
        </SurfaceCard>
      </div>

      <PermissionGate v-if="isConfirmed && !sale.treatment_id" permission="treatments.start">
        <Button :loading="startingTreatment" @click="treatmentMutation.mutate()">
          Abrir tratamento
        </Button>
      </PermissionGate>
      <template v-else-if="sale.treatment_id">
        <Banner variant="info" title="Tratamento aberto">
          O caso clínico já foi iniciado a partir desta venda.
        </Banner>
        <div class="flex flex-wrap gap-2">
          <PermissionGate permission="appointments.manage">
            <Button
              @click="
                router.push({
                  name: 'appointments-new',
                  query: { treatment_id: String(sale.treatment_id) },
                })
              "
            >
              Agendar sessão
            </Button>
          </PermissionGate>
          <PermissionGate permission="appointments.view">
            <Button variant="secondary" @click="router.push({ name: 'appointments' })">
              Ver agenda
            </Button>
          </PermissionGate>
        </div>
      </template>

      <SaleBudgetsPanel :sale-id="sale.id" :can-create="isDraft && items.length > 0" />
    </template>

    <ConfirmDialog
      v-model:open="cancelOpen"
      title="Cancelar esta venda?"
      description="O histórico permanece. Não dá para desfazer."
      confirm-label="Cancelar venda"
      @confirm="cancelMutation.mutate()"
    />
  </div>
</template>
