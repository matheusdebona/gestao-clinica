<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ListCard from '@/components/ui/ListCard.vue'
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import {
  listAppointmentConfirmations,
  logAppointmentWhatsAppConfirmation,
  setAppointmentConfirmationStatus,
} from '@/features/appointments/api'
import {
  CONFIRMATION_STATUS_BADGE,
  CONFIRMATION_STATUS_LABELS,
} from '@/features/appointments/labels'
import { useWhatsAppLinks } from '@/features/settings/useWhatsAppLinks'
import { formatIsoDate, formatTime } from '@/lib/formatters'
import { openWhatsApp } from '@/lib/whatsapp'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { Appointment, AppointmentConfirmationStatus } from '@/types/appointment'
import { ApiError } from '@/types/user'

const props = withDefaults(defineProps<{ limit?: number; showAgendaLink?: boolean }>(), {
  limit: undefined,
  showAgendaLink: false,
})

const auth = useAuthStore()
const toast = useToastStore()
const router = useRouter()
const queryClient = useQueryClient()
const { appointmentUrl } = useWhatsAppLinks()

const { data, isPending, isError } = useQuery({
  queryKey: ['appointments', 'confirmations', 'tomorrow'],
  queryFn: () => listAppointmentConfirmations(),
  enabled: computed(() => auth.can('appointments.view')),
})

const items = computed(() => {
  const list = data.value?.data ?? []
  return props.limit ? list.slice(0, props.limit) : list
})
const hidden = computed(() => (data.value?.data.length ?? 0) - items.value.length)
const counts = computed(() => data.value?.meta.counts)
const toConfirm = computed(() =>
  counts.value ? counts.value.pending + counts.value.not_confirmed : 0,
)

const statusOptions = (
  Object.keys(CONFIRMATION_STATUS_LABELS) as AppointmentConfirmationStatus[]
).map((value) => ({ value, label: CONFIRMATION_STATUS_LABELS[value] }))

const statusMutation = useMutation({
  mutationFn: (input: { id: number; status: AppointmentConfirmationStatus }) =>
    setAppointmentConfirmationStatus(input.id, input.status),
  onSuccess: async (appointment) => {
    toast.success(CONFIRMATION_STATUS_LABELS[appointment.confirmation_status ?? 'pending'])
    await queryClient.invalidateQueries({ queryKey: ['appointments'] })
  },
  onError: (error) => {
    toast.error(
      error instanceof ApiError ? error.message : 'Não foi possível salvar a confirmação.',
    )
  },
})

function onStatus(item: Appointment, value: string) {
  if (value === item.confirmation_status) {
    return
  }
  statusMutation.mutate({
    id: item.id,
    status: value as AppointmentConfirmationStatus,
  })
}

function sendWhatsApp(item: Appointment) {
  const url = appointmentUrl(item)
  if (!url) {
    toast.error('WhatsApp do paciente inválido.')
    return
  }
  openWhatsApp(url)
  void logAppointmentWhatsAppConfirmation(item.id)
    .then(() => queryClient.invalidateQueries({ queryKey: ['appointments'] }))
    .catch(() => undefined)
}

function meta(item: Appointment): string {
  const bits = [formatTime(item.scheduled_at), item.professional?.name ?? 'Profissional']
  if (item.confirmation_sent_at) {
    bits.push('WhatsApp aberto')
  }
  return bits.join(' · ')
}

function openAppointment(id: number) {
  void router.push({ name: 'appointments-show', params: { id: String(id) } })
}
</script>

<template>
  <SurfaceCard>
    <div class="flex items-baseline justify-between gap-3">
      <h2>Confirmar amanhã</h2>
      <p v-if="data" class="text-[13px] text-muted">
        {{ formatIsoDate(data.meta.date) }}
      </p>
    </div>
    <p v-if="counts && counts.total > 0" class="mt-1 text-[13px] text-muted">
      {{ counts.total === 1 ? '1 sessão' : `${counts.total} sessões` }} ·
      {{ counts.confirmed }}
      {{ counts.confirmed === 1 ? 'confirmada' : 'confirmadas' }} · {{ toConfirm }} a confirmar
      <template v-if="counts.declined"> · {{ counts.declined }} desmarcou</template>
    </p>

    <div v-if="isPending" class="mt-3 flex flex-col gap-3">
      <Skeleton class="h-12" />
      <Skeleton class="h-12" />
    </div>
    <p v-else-if="isError" class="mt-2 text-[15px] text-danger">
      Não foi possível carregar as sessões de amanhã.
    </p>
    <EmptyState
      v-else-if="items.length === 0"
      class="mt-2"
      title="Nada para confirmar"
      description="Nenhuma sessão agendada para amanhã."
    />
    <ul v-else class="mt-3 flex flex-col divide-y divide-border-divider">
      <li
        v-for="item in items"
        :key="item.id"
        class="flex flex-col gap-2 py-2 first:pt-0 last:pb-1"
      >
        <ListCard
          :title="item.client?.name ?? `Paciente #${item.client_id}`"
          :meta="meta(item)"
          :badge="CONFIRMATION_STATUS_LABELS[item.confirmation_status ?? 'pending']"
          :badge-variant="CONFIRMATION_STATUS_BADGE[item.confirmation_status ?? 'pending']"
          @action="openAppointment(item.id)"
        />
        <PermissionGate :permission="['appointments.manage', 'treatments.manage']" mode="any">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <Button
              mobile-block
              variant="secondary"
              :disabled="!item.client?.whatsapp"
              @click="sendWhatsApp(item)"
            >
              {{
                item.confirmation_sent_at
                  ? 'Enviar de novo pelo WhatsApp'
                  : 'Confirmar pelo WhatsApp'
              }}
            </Button>
            <div class="sm:w-60">
              <Select
                :id="`confirmation-${item.id}`"
                :model-value="item.confirmation_status ?? 'pending'"
                :options="statusOptions"
                :disabled="statusMutation.isPending.value"
                @update:model-value="onStatus(item, $event)"
              />
            </div>
          </div>
        </PermissionGate>
      </li>
    </ul>

    <div v-if="showAgendaLink && (hidden > 0 || items.length > 0)" class="mt-3">
      <Button mobile-block variant="ghost" @click="router.push({ name: 'appointments' })">
        {{ hidden > 0 ? `Ver mais ${hidden} na Agenda` : 'Abrir Agenda' }}
      </Button>
    </div>
  </SurfaceCard>
</template>
