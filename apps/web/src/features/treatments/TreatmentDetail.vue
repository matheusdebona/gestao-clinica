<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import MoneyDisplay from '@/components/patterns/MoneyDisplay.vue'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { APPOINTMENT_STATUS_LABELS } from '@/features/appointments/labels'
import {
  cancelTreatment,
  completeTreatment,
  getTreatment,
  getTreatmentFulfillment,
} from '@/features/treatments/api'
import { TREATMENT_STATUS_LABELS } from '@/features/treatments/labels'
import { formatBRL, formatDateTime, formatQty } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { TreatmentSessionSummary } from '@/types/treatment'
import type { AppointmentStatus } from '@/types/appointment'
import { ApiError } from '@/types/user'

const props = defineProps<{
  treatmentId: number
}>()

const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()
const queryClient = useQueryClient()
const completeOpen = ref(false)
const cancelOpen = ref(false)

const idRef = computed(() => props.treatmentId)
const canView = computed(() => auth.can('treatments.view'))

const { data: treatment, isPending, isError } = useQuery({
  queryKey: ['treatments', idRef],
  queryFn: () => getTreatment(idRef.value),
  enabled: canView,
})

const fulfillmentQuery = useQuery({
  queryKey: ['treatments', idRef, 'fulfillment'],
  queryFn: () => getTreatmentFulfillment(idRef.value),
  enabled: canView,
})

const isOpen = computed(() => treatment.value?.status === 'open')
const appointments = computed(() => treatment.value?.appointments ?? [])
const fulfillmentItems = computed(() => fulfillmentQuery.data.value?.items ?? [])
const hasActiveSession = computed(() =>
  appointments.value.some((item) => item.status === 'scheduled' || item.status === 'in_progress'),
)
const hasCompletedSession = computed(() =>
  appointments.value.some((item) => item.status === 'completed'),
)
const remainingQty = computed(() =>
  fulfillmentItems.value.reduce((sum, item) => sum + Number(item.remaining_quantity || 0), 0),
)

const completeMutation = useMutation({
  mutationFn: () => completeTreatment(props.treatmentId),
  onSuccess: async () => {
    toast.success('Tratamento concluído')
    await queryClient.invalidateQueries({ queryKey: ['treatments'] })
  },
  onError: (error) => {
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível concluir.')
  },
})

const cancelMutation = useMutation({
  mutationFn: () => cancelTreatment(props.treatmentId),
  onSuccess: async () => {
    toast.success('Tratamento cancelado')
    await queryClient.invalidateQueries({ queryKey: ['treatments'] })
    await queryClient.invalidateQueries({ queryKey: ['appointments'] })
  },
  onError: (error) => {
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível cancelar.')
  },
})

function goBack() {
  void router.push({ name: 'treatments' })
}

function openAppointment(id: number) {
  void router.push({ name: 'appointments-show', params: { id: String(id) } })
}

function openConsume(id: number) {
  void router.push({ name: 'appointments-consume', params: { id: String(id) } })
}

function goSchedule() {
  void router.push({
    name: 'appointments-new',
    query: { treatment_id: String(props.treatmentId) },
  })
}

function sessionStatusLabel(status: string) {
  if (status in APPOINTMENT_STATUS_LABELS) {
    return APPOINTMENT_STATUS_LABELS[status as AppointmentStatus]
  }
  return status
}

function sessionMeta(item: TreatmentSessionSummary) {
  const when = formatDateTime(item.scheduled_at)
  const professional = item.professional?.name
  return professional ? `${when} · ${professional}` : when
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="treatment?.client?.name ?? 'Tratamento'">
      <template #actions>
        <Button variant="ghost" @click="goBack">Voltar</Button>
      </template>
    </PageHeader>

    <Banner v-if="!canView" variant="danger" title="Sem permissão">
      Você não pode ver este tratamento.
    </Banner>

    <Banner v-else-if="isError" variant="danger" title="Não foi possível carregar">
      O tratamento pode ter sido removido ou você não tem permissão.
    </Banner>

    <SurfaceCard v-else-if="isPending">
      <Skeleton class="h-6 w-40" />
      <Skeleton class="mt-3 h-5 w-56" />
    </SurfaceCard>

    <template v-else-if="treatment">
      <Banner v-if="treatment.status === 'cancelled'" variant="warning" title="Cancelado">
        Este caso clínico foi cancelado. O estoque de sessões concluídas não é estornado aqui.
      </Banner>
      <Banner v-else-if="treatment.status === 'completed'" variant="success" title="Concluído">
        Todas as sessões ativas foram encerradas.
      </Banner>
      <Banner v-else-if="hasActiveSession" variant="info" title="Sessão em aberto">
        Conclua ou cancele as sessões agendadas ou em atendimento antes de fechar o tratamento.
      </Banner>

      <SurfaceCard>
        <dl class="flex flex-col gap-4">
          <div>
            <dt class="text-[13px] text-muted">Status</dt>
            <dd class="mt-0.5 text-[15px] text-title">{{ TREATMENT_STATUS_LABELS[treatment.status] }}</dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Cliente</dt>
            <dd class="mt-0.5 text-[15px] text-title">{{ treatment.client?.name ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Venda</dt>
            <dd class="mt-0.5 text-[15px] text-title">
              <RouterLink
                :to="{ name: 'sales-show', params: { id: String(treatment.sale_id) } }"
                class="text-brand"
              >
                Venda #{{ treatment.sale_id }}
              </RouterLink>
            </dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Custo total</dt>
            <dd class="mt-0.5"><MoneyDisplay :value="treatment.total_cost" /></dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Notas</dt>
            <dd class="mt-0.5 whitespace-pre-wrap text-[15px] text-title">{{ treatment.notes || '—' }}</dd>
          </div>
        </dl>
      </SurfaceCard>

      <div>
        <h2 class="mb-3">Fulfillment</h2>
        <SurfaceCard v-if="fulfillmentQuery.isPending">
          <Skeleton class="h-5 w-48" />
        </SurfaceCard>
        <SurfaceCard v-else-if="fulfillmentItems.length === 0" :padding="false">
          <p class="px-5 py-4 text-[15px] text-muted">Nenhum item vendido neste caso.</p>
        </SurfaceCard>
        <SurfaceCard v-else>
          <ul class="flex flex-col gap-4">
            <li v-for="item in fulfillmentItems" :key="item.sale_item_id" class="flex flex-col gap-1">
              <p class="text-[15px] font-medium text-title">{{ item.product_name }}</p>
              <p class="text-[13px] text-muted">
                Vendido {{ formatQty(item.sold_quantity) }} · Consumido
                {{ formatQty(item.consumed_quantity) }} · Restante
                {{ formatQty(item.remaining_quantity) }} · Estoque
                {{ formatQty(item.stock_quantity) }}
              </p>
            </li>
          </ul>
        </SurfaceCard>
      </div>

      <div>
        <div class="mb-3 flex items-center justify-between gap-3">
          <h2>Sessões</h2>
          <PermissionGate v-if="isOpen" permission="appointments.manage">
            <Button variant="secondary" @click="goSchedule">Agendar sessão</Button>
          </PermissionGate>
        </div>
        <SurfaceCard v-if="appointments.length === 0" :padding="false">
          <EmptyState
            title="Nenhuma sessão"
            description="Agende pela Agenda a partir deste tratamento."
          />
        </SurfaceCard>
        <SurfaceCard v-else>
          <ul class="flex flex-col gap-4">
            <li
              v-for="item in appointments"
              :key="item.id"
              class="flex flex-col gap-2 border-b border-border-divider pb-4 last:border-b-0 last:pb-0"
            >
              <div>
                <p class="text-[15px] font-medium text-title">
                  {{ sessionStatusLabel(item.status) }}
                </p>
                <p class="mt-0.5 text-[13px] text-muted">{{ sessionMeta(item) }}</p>
                <p v-if="item.status === 'completed'" class="mt-0.5 text-[13px] text-muted">
                  Custo {{ formatBRL(item.total_cost) }}
                </p>
              </div>
              <div class="flex flex-wrap gap-2">
                <Button variant="secondary" @click="openAppointment(item.id)">Ver sessão</Button>
                <PermissionGate v-if="item.status === 'in_progress'" permission="treatments.consume">
                  <Button @click="openConsume(item.id)">Registrar consumo</Button>
                </PermissionGate>
              </div>
            </li>
          </ul>
        </SurfaceCard>
      </div>

      <div v-if="isOpen" class="flex flex-wrap gap-2">
        <PermissionGate permission="treatments.complete">
          <Button :disabled="hasActiveSession" @click="completeOpen = true">
            Concluir tratamento
          </Button>
        </PermissionGate>
        <PermissionGate v-if="!hasCompletedSession" permission="treatments.cancel">
          <Button variant="destructive" @click="cancelOpen = true">Cancelar tratamento</Button>
        </PermissionGate>
      </div>
    </template>

    <ConfirmDialog
      v-model:open="completeOpen"
      title="Concluir este tratamento?"
      :description="
        remainingQty > 0
          ? 'Ainda há saldo da venda sem consumir. Isso não bloqueia a conclusão.'
          : 'Não dá para desfazer. Sessões ativas precisam estar encerradas.'
      "
      confirm-label="Concluir"
      confirm-variant="primary"
      @confirm="completeMutation.mutate()"
    />
    <ConfirmDialog
      v-model:open="cancelOpen"
      title="Cancelar este tratamento?"
      description="Só é possível se nenhuma sessão foi concluída. Sessões agendadas também são canceladas."
      confirm-label="Cancelar tratamento"
      @confirm="cancelMutation.mutate()"
    />
  </div>
</template>
