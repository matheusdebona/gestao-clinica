<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import AppointmentForm from '@/features/appointments/AppointmentForm.vue'
import {
  cancelAppointment,
  getAppointment,
  listProfessionals,
  startAppointment,
  updateAppointment,
} from '@/features/appointments/api'
import { APPOINTMENT_STATUS_LABELS } from '@/features/appointments/labels'
import { listTreatments } from '@/features/treatments/api'
import { formatBRL, formatDateTime } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { AppointmentPayload, StockWarning } from '@/types/appointment'
import { ApiError } from '@/types/user'

const props = defineProps<{
  appointmentId: number
}>()

const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()
const queryClient = useQueryClient()
const formRef = ref<{ setErrors: (errors: Record<string, string>) => void } | null>(null)
const cancelOpen = ref(false)
const startOpen = ref(false)

const idRef = computed(() => props.appointmentId)
const canView = computed(() => auth.can('appointments.view'))

const { data: appointment, isPending, isError } = useQuery({
  queryKey: ['appointments', idRef],
  queryFn: () => getAppointment(idRef.value),
  enabled: canView,
})

const professionalsQuery = useQuery({
  queryKey: ['professionals'],
  queryFn: listProfessionals,
  enabled: computed(() => auth.can('appointments.manage')),
})

const treatmentsQuery = useQuery({
  queryKey: ['treatments', 'open'],
  queryFn: () => listTreatments({ status: 'open', per_page: 100 }),
  enabled: computed(() => auth.can('appointments.manage')),
})

const isScheduled = computed(() => appointment.value?.status === 'scheduled')
const canCancelStatus = computed(
  () => appointment.value?.status === 'scheduled' || appointment.value?.status === 'in_progress',
)

const stockWarnings = computed((): StockWarning[] => {
  const raw = appointment.value?.stock_warning
  if (!Array.isArray(raw)) {
    return []
  }
  return raw.filter((item): item is StockWarning => typeof item === 'object' && item !== null && 'product_name' in item)
})

const treatmentsForForm = computed(() => {
  const current = appointment.value
  const open = treatmentsQuery.data.value?.data ?? []
  if (!current) {
    return open
  }
  if (open.some((item) => item.id === current.treatment_id)) {
    return open
  }
  return [
    {
      id: current.treatment_id,
      clinic_id: current.clinic_id,
      sale_id: current.treatment && 'sale_id' in current.treatment ? current.treatment.sale_id : 0,
      client_id: current.client_id,
      status: 'open' as const,
      notes: null,
      client: current.client ?? null,
    },
    ...open,
  ]
})

const professionals = computed(() => professionalsQuery.data.value ?? [])

const rescheduleMutation = useMutation({
  mutationFn: (payload: AppointmentPayload) => updateAppointment(props.appointmentId, payload),
  onSuccess: async () => {
    toast.success('Sessão remarcada')
    await queryClient.invalidateQueries({ queryKey: ['appointments'] })
  },
  onError: (error) => {
    if (error instanceof ApiError && error.status === 422) {
      const mapped: Record<string, string> = {}
      for (const [field, messages] of Object.entries(error.errors)) {
        mapped[field] = messages[0] ?? error.message
      }
      formRef.value?.setErrors(mapped)
      if (!Object.keys(mapped).length) {
        toast.error(error.message)
      }
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível remarcar.')
  },
})

const reschedulePending = computed(() => rescheduleMutation.isPending.value)

const startMutation = useMutation({
  mutationFn: () => startAppointment(props.appointmentId),
  onSuccess: async (result) => {
    toast.success('Atendimento iniciado')
    await queryClient.invalidateQueries({ queryKey: ['appointments'] })
    const warnings = result.stock_warnings ?? []
    if (warnings.length > 0) {
      toast.info('Há avisos de estoque nesta sessão. Eles não bloqueiam o atendimento.')
    }
  },
  onError: (error) => {
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível iniciar.')
  },
})

const cancelMutation = useMutation({
  mutationFn: () => cancelAppointment(props.appointmentId),
  onSuccess: async () => {
    toast.success('Sessão cancelada')
    await queryClient.invalidateQueries({ queryKey: ['appointments'] })
  },
  onError: (error) => {
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível cancelar.')
  },
})

function onReschedule(payload: AppointmentPayload & { treatment_id: number }) {
  const { treatment_id: _treatmentId, ...body } = payload
  rescheduleMutation.mutate(body)
}

function goBack() {
  void router.push({ name: 'appointments' })
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="appointment?.client?.name ?? 'Sessão'">
      <template #actions>
        <Button variant="ghost" @click="goBack">Voltar</Button>
      </template>
    </PageHeader>

    <Banner v-if="!canView" variant="danger" title="Sem permissão">
      Você não pode ver esta sessão.
    </Banner>

    <Banner v-else-if="isError" variant="danger" title="Não foi possível carregar">
      A sessão pode ter sido removida ou você não tem permissão.
    </Banner>

    <SurfaceCard v-else-if="isPending">
      <Skeleton class="h-6 w-40" />
      <Skeleton class="mt-3 h-5 w-56" />
    </SurfaceCard>

    <template v-else-if="appointment">
      <Banner v-if="appointment.status === 'in_progress'" variant="info" title="Em atendimento">
        Registre o consumo desta sessão para baixar o estoque ao concluir.
      </Banner>
      <Banner v-else-if="appointment.status === 'completed'" variant="success" title="Concluída">
        Estoque baixado. Custo da sessão {{ formatBRL(appointment.total_cost) }}.
      </Banner>
      <Banner v-else-if="appointment.status === 'cancelled'" variant="warning" title="Cancelada">
        Esta sessão foi cancelada. O estoque não foi alterado.
      </Banner>
      <Banner
        v-for="warning in stockWarnings"
        :key="warning.product_id"
        variant="warning"
        :title="warning.product_name"
      >
        Estoque {{ warning.stock_quantity }} para sugerido {{ warning.suggested_quantity }}. O aviso não bloqueia o
        atendimento.
      </Banner>

      <SurfaceCard>
        <dl class="flex flex-col gap-4">
          <div>
            <dt class="text-[13px] text-muted">Status</dt>
            <dd class="mt-0.5 text-[15px] text-title">{{ APPOINTMENT_STATUS_LABELS[appointment.status] }}</dd>
          </div>
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
          <div>
            <dt class="text-[13px] text-muted">Tratamento</dt>
            <dd class="mt-0.5 text-[15px] text-title">
              <RouterLink
                :to="{ name: 'treatments-show', params: { id: String(appointment.treatment_id) } }"
                class="text-brand"
              >
                Tratamento #{{ appointment.treatment_id }}
              </RouterLink>
            </dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Duração</dt>
            <dd class="mt-0.5 text-[15px] text-title">
              {{ appointment.duration_minutes ? `${appointment.duration_minutes} min` : '—' }}
            </dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Notas</dt>
            <dd class="mt-0.5 whitespace-pre-wrap text-[15px] text-title">{{ appointment.notes || '—' }}</dd>
          </div>
          <div v-if="appointment.status === 'completed'">
            <dt class="text-[13px] text-muted">Custo da sessão</dt>
            <dd class="mt-0.5 text-[15px] text-title">{{ formatBRL(appointment.total_cost) }}</dd>
          </div>
        </dl>
      </SurfaceCard>

      <div class="flex flex-wrap gap-2">
        <PermissionGate v-if="isScheduled" permission="appointments.start">
          <Button @click="startOpen = true">Iniciar atendimento</Button>
        </PermissionGate>
        <PermissionGate v-if="appointment.status === 'in_progress'" permission="treatments.consume">
          <Button @click="router.push({ name: 'appointments-consume', params: { id: String(appointment.id) } })">
            Registrar consumo
          </Button>
        </PermissionGate>
        <PermissionGate v-if="canCancelStatus" permission="appointments.cancel">
          <Button variant="destructive" @click="cancelOpen = true">Cancelar sessão</Button>
        </PermissionGate>
      </div>

      <PermissionGate v-if="isScheduled" permission="appointments.manage">
        <div>
          <h2 class="mb-3">Remarcar</h2>
          <SurfaceCard>
            <AppointmentForm
              ref="formRef"
              :appointment="appointment"
              :treatments="treatmentsForForm"
              :professionals="professionals"
              lock-treatment
              submit-label="Salvar horário"
              :loading="reschedulePending"
              @submit="onReschedule"
              @cancel="goBack"
            />
          </SurfaceCard>
        </div>
      </PermissionGate>
    </template>

    <ConfirmDialog
      v-model:open="startOpen"
      title="Iniciar este atendimento?"
      description="A sessão passa para em atendimento. O estoque não é baixado agora."
      confirm-label="Iniciar"
      confirm-variant="primary"
      @confirm="startMutation.mutate()"
    />
    <ConfirmDialog
      v-model:open="cancelOpen"
      title="Cancelar esta sessão?"
      description="O horário é liberado. Não há efeito em estoque."
      confirm-label="Cancelar sessão"
      @confirm="cancelMutation.mutate()"
    />
  </div>
</template>
