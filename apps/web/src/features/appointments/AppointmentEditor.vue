<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Banner from '@/components/ui/Banner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import AppointmentForm from '@/features/appointments/AppointmentForm.vue'
import { createAppointment, listProfessionals } from '@/features/appointments/api'
import { listTreatments } from '@/features/treatments/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { AppointmentPayload } from '@/types/appointment'
import { ApiError } from '@/types/user'

const route = useRoute()
const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()
const queryClient = useQueryClient()
const formRef = ref<{ setErrors: (errors: Record<string, string>) => void } | null>(null)

const allowed = computed(() => auth.can('appointments.manage'))

const initialTreatmentId = computed(() => {
  const raw = route.query.treatment_id
  const value = Array.isArray(raw) ? raw[0] : raw
  if (!value) {
    return null
  }
  const parsed = Number(value)
  return Number.isInteger(parsed) ? parsed : null
})

const treatmentsQuery = useQuery({
  queryKey: ['treatments', 'open'],
  queryFn: () => listTreatments({ status: 'open', per_page: 100 }),
  enabled: allowed,
})

const professionalsQuery = useQuery({
  queryKey: ['professionals'],
  queryFn: listProfessionals,
  enabled: allowed,
})

const openTreatments = computed(() => treatmentsQuery.data.value?.data ?? [])
const professionals = computed(() => professionalsQuery.data.value ?? [])
const catalogsPending = computed(
  () => treatmentsQuery.isPending.value || professionalsQuery.isPending.value,
)

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: (payload: AppointmentPayload & { treatment_id: number }) => {
    const { treatment_id: treatmentId, ...body } = payload
    return createAppointment(treatmentId, body)
  },
  onSuccess: async (created) => {
    toast.success('Sessão agendada')
    await queryClient.invalidateQueries({ queryKey: ['appointments'] })
    await router.push({ name: 'appointments-show', params: { id: String(created.id) } })
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
    if (error instanceof ApiError && error.status === 403) {
      toast.error('Sem permissão.')
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível agendar.')
  },
})

function onCancel() {
  void router.push({ name: 'appointments' })
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader title="Nova sessão" description="Tratamento aberto, profissional e horário." />

    <Banner v-if="!allowed" variant="danger" title="Sem permissão">
      Você não pode criar sessões na agenda.
    </Banner>

    <SurfaceCard v-else-if="catalogsPending">
      <Skeleton class="h-11" />
      <Skeleton class="mt-3 h-11" />
      <Skeleton class="mt-3 h-11" />
    </SurfaceCard>

    <Banner
      v-else-if="openTreatments.length === 0"
      variant="warning"
      title="Nenhum tratamento aberto"
    >
      Abra um tratamento a partir de uma venda confirmada antes de agendar.
    </Banner>

    <SurfaceCard v-else>
      <AppointmentForm
        ref="formRef"
        :treatments="openTreatments"
        :professionals="professionals"
        :initial-treatment-id="initialTreatmentId"
        submit-label="Agendar"
        :loading="saving"
        @submit="save"
        @cancel="onCancel"
      />
    </SurfaceCard>
  </div>
</template>
