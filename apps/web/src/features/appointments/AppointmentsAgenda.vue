<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ChevronLeft, ChevronRight } from '@lucide/vue'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import AgendaEventCard from '@/components/patterns/AgendaEventCard.vue'
import ClientSearchBar from '@/components/patterns/ClientSearchBar.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormField from '@/components/ui/FormField.vue'
import IconButton from '@/components/ui/IconButton.vue'
import Input from '@/components/ui/Input.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Tabs from '@/components/ui/Tabs.vue'
import { listAppointments, listProfessionals } from '@/features/appointments/api'
import {
  addDays,
  endOfDay,
  formatDayHeading,
  formatWeekdayDate,
  fromDateInput,
  isSameDay,
  startOfDay,
  startOfWeekMonday,
  toDateInput,
  weekDays,
} from '@/features/appointments/calendar'
import { APPOINTMENT_STATUS_BADGE, APPOINTMENT_STATUS_LABELS } from '@/features/appointments/labels'
import { formatTime } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import type { Appointment } from '@/types/appointment'

const router = useRouter()
const auth = useAuthStore()

const view = ref('day')
const cursor = ref(startOfDay(new Date()))
const status = ref('')
const professionalId = ref('')
const q = ref('')
const searchInput = ref('')

const viewItems = [
  { value: 'day', label: 'Dia' },
  { value: 'week', label: 'Semana' },
]

const statusOptions = [
  { value: '', label: 'Todos os status' },
  { value: 'scheduled', label: APPOINTMENT_STATUS_LABELS.scheduled },
  { value: 'in_progress', label: APPOINTMENT_STATUS_LABELS.in_progress },
  { value: 'completed', label: APPOINTMENT_STATUS_LABELS.completed },
  { value: 'cancelled', label: APPOINTMENT_STATUS_LABELS.cancelled },
]

const dateInput = computed({
  get: () => toDateInput(cursor.value),
  set: (value: string) => {
    cursor.value = startOfDay(fromDateInput(value))
  },
})

const range = computed(() => {
  if (view.value === 'week') {
    const start = startOfWeekMonday(cursor.value)
    return { from: start.toISOString(), to: endOfDay(addDays(start, 6)).toISOString() }
  }
  return {
    from: startOfDay(cursor.value).toISOString(),
    to: endOfDay(cursor.value).toISOString(),
  }
})

const heading = computed(() => {
  if (view.value === 'week') {
    const days = weekDays(cursor.value)
    return `${formatWeekdayDate(days[0])} – ${formatWeekdayDate(days[6])}`
  }
  return formatDayHeading(cursor.value)
})

const professionalsQuery = useQuery({
  queryKey: ['professionals'],
  queryFn: listProfessionals,
  enabled: computed(() => auth.can('appointments.view')),
})

const professionalOptions = computed(() => [
  { value: '', label: 'Todos os profissionais' },
  ...(professionalsQuery.data.value ?? []).map((user) => ({
    value: String(user.id),
    label: user.name,
  })),
])

const {
  data: listData,
  isPending,
  isError,
} = useQuery({
  queryKey: ['appointments', range, status, professionalId, q],
  queryFn: () =>
    listAppointments({
      from: range.value.from,
      to: range.value.to,
      status: status.value || undefined,
      professional_user_id: professionalId.value ? Number(professionalId.value) : undefined,
      q: q.value || undefined,
      per_page: 100,
    }),
  enabled: computed(() => auth.can('appointments.view')),
})

const appointments = computed(() => listData.value?.data ?? [])

const days = computed(() => weekDays(cursor.value))

function eventsOn(day: Date): Appointment[] {
  return appointments.value.filter((item) => {
    if (!item.scheduled_at) {
      return false
    }
    return isSameDay(new Date(item.scheduled_at), day)
  })
}

function eventMeta(item: Appointment): string {
  const time = formatTime(item.scheduled_at)
  const professional = item.professional?.name ?? 'Profissional'
  const treatment = `Tratamento #${item.treatment_id}`
  return `${time} · ${professional} · ${treatment}`
}

function openAppointment(id: number) {
  void router.push({ name: 'appointments-show', params: { id: String(id) } })
}

function goNew() {
  void router.push({ name: 'appointments-new' })
}

function shift(amount: number) {
  cursor.value = addDays(cursor.value, view.value === 'week' ? amount * 7 : amount)
}

function goToday() {
  cursor.value = startOfDay(new Date())
}

function onClientSearch(value: string) {
  q.value = value
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[960px] flex-col gap-6">
    <PageHeader title="Agenda" :description="heading">
      <template #actions>
        <PermissionGate permission="appointments.manage">
          <Button @click="goNew">Nova sessão</Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="!auth.can('appointments.view')" variant="danger" title="Sem permissão">
      Você não pode ver a agenda da clínica.
    </Banner>

    <template v-else>
      <div class="flex flex-wrap items-center gap-2">
        <IconButton label="Anterior" @click="shift(-1)">
          <ChevronLeft class="size-4" :stroke-width="1.75" />
        </IconButton>
        <Button variant="secondary" @click="goToday">Hoje</Button>
        <IconButton label="Próximo" @click="shift(1)">
          <ChevronRight class="size-4" :stroke-width="1.75" />
        </IconButton>
        <div class="min-w-[11rem] flex-1">
          <Input v-model="dateInput" type="date" />
        </div>
        <div class="hidden md:block">
          <Tabs v-model="view" :items="viewItems" />
        </div>
      </div>

      <FormField label="Status">
        <Select v-model="status" :options="statusOptions" />
      </FormField>

      <FormField label="Profissional">
        <Select v-model="professionalId" :options="professionalOptions" />
      </FormField>

      <ClientSearchBar
        v-model="searchInput"
        placeholder="Filtrar por cliente"
        @search="onClientSearch"
      />

      <Banner v-if="isError" variant="danger" title="Não foi possível carregar">
        Tente de novo em instantes.
      </Banner>

      <SurfaceCard v-else-if="isPending" :padding="false">
        <div class="flex flex-col gap-3 p-5">
          <Skeleton class="h-12" />
          <Skeleton class="h-12" />
          <Skeleton class="h-12" />
        </div>
      </SurfaceCard>

      <div :class="view === 'week' ? 'md:hidden' : ''">
        <SurfaceCard v-if="!isPending && !isError && eventsOn(cursor).length === 0" :padding="false">
          <EmptyState
            title="Nenhuma sessão neste dia"
            description="Crie uma sessão ou avance o calendário."
          >
            <template #action>
              <PermissionGate permission="appointments.manage">
                <Button @click="goNew">Nova sessão</Button>
              </PermissionGate>
            </template>
          </EmptyState>
        </SurfaceCard>
        <SurfaceCard v-else-if="!isPending && !isError" :padding="false">
          <div class="divide-y divide-border-divider px-5 py-2">
            <AgendaEventCard
              v-for="item in eventsOn(cursor)"
              :key="item.id"
              :title="item.client?.name ?? `Cliente #${item.client_id}`"
              :meta="eventMeta(item)"
              :badge="APPOINTMENT_STATUS_LABELS[item.status]"
              :badge-variant="APPOINTMENT_STATUS_BADGE[item.status]"
              @action="openAppointment(item.id)"
            />
          </div>
        </SurfaceCard>
      </div>

      <div v-if="view === 'week'" class="hidden gap-3 md:grid md:grid-cols-7">
        <SurfaceCard
          v-for="day in days"
          :key="day.toISOString()"
          :padding="false"
          :class="isSameDay(day, new Date()) ? 'border-brand' : ''"
        >
          <div class="border-b border-border-divider px-3 py-2">
            <p class="text-[13px] font-medium text-title">{{ formatWeekdayDate(day) }}</p>
          </div>
          <div v-if="eventsOn(day).length === 0" class="px-3 py-4">
            <p class="text-[13px] text-muted">Livre</p>
          </div>
          <div v-else class="divide-y divide-border-divider px-3 py-1">
            <AgendaEventCard
              v-for="item in eventsOn(day)"
              :key="item.id"
              :title="item.client?.name ?? `Cliente #${item.client_id}`"
              :meta="eventMeta(item)"
              :badge="APPOINTMENT_STATUS_LABELS[item.status]"
              :badge-variant="APPOINTMENT_STATUS_BADGE[item.status]"
              @action="openAppointment(item.id)"
            />
          </div>
        </SurfaceCard>
      </div>
    </template>
  </div>
</template>
