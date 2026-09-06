<script setup lang="ts">
import { useForm } from 'vee-validate'
import { computed, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Textarea from '@/components/ui/Textarea.vue'
import {
  appointmentFormSchema,
  appointmentToForm,
  emptyAppointmentForm,
  toAppointmentPayload,
  type AppointmentFormValues,
} from '@/features/appointments/schema'
import type { Appointment, AppointmentPayload } from '@/types/appointment'
import type { Treatment } from '@/types/treatment'
import type { ClinicUser } from '@/types/team-user'

const props = withDefaults(
  defineProps<{
    appointment?: Appointment | null
    treatments?: Treatment[]
    professionals?: ClinicUser[]
    lockTreatment?: boolean
    submitLabel?: string
    loading?: boolean
    initialTreatmentId?: number | null
  }>(),
  {
    appointment: null,
    treatments: () => [],
    professionals: () => [],
    lockTreatment: false,
    submitLabel: 'Salvar',
    loading: false,
    initialTreatmentId: null,
  },
)

const emit = defineEmits<{
  submit: [payload: AppointmentPayload & { treatment_id: number }]
  cancel: []
}>()

const { defineField, handleSubmit, errors, setErrors, resetForm } = useForm({
  validationSchema: appointmentFormSchema,
  initialValues: emptyAppointmentForm(),
})

const [treatmentId] = defineField('treatment_id')
const [professionalId] = defineField('professional_user_id')
const [scheduledAt, scheduledAttrs] = defineField('scheduled_at')
const [duration, durationAttrs] = defineField('duration_minutes')
const [notes, notesAttrs] = defineField('notes')

const treatmentOptions = computed(() =>
  props.treatments.map((treatment) => ({
    value: String(treatment.id),
    label: treatment.client?.name
      ? `${treatment.client.name} · tratamento #${treatment.id}`
      : `Tratamento #${treatment.id}`,
  })),
)

const professionalOptions = computed(() =>
  props.professionals.map((user) => ({
    value: String(user.id),
    label: user.name,
  })),
)

const selectedTreatment = computed(() =>
  props.treatments.find((treatment) => String(treatment.id) === treatmentId.value) ?? null,
)

const durationHint = computed(() => {
  const minutes = selectedTreatment.value?.client?.service_duration_minutes
  if (minutes) {
    return `Padrão do cliente: ${minutes} min. Vazio usa esse valor (ou 60 min).`
  }
  return 'Vazio usa a duração do cliente ou 60 minutos.'
})

watch(
  () => props.appointment,
  (appointment) => {
    if (appointment) {
      resetForm({ values: appointmentToForm(appointment) })
      return
    }
    const defaults = emptyAppointmentForm()
    if (props.initialTreatmentId) {
      defaults.treatment_id = String(props.initialTreatmentId)
    }
    resetForm({ values: defaults })
  },
  { immediate: true },
)

watch(
  () => props.initialTreatmentId,
  (id) => {
    if (!props.appointment && id) {
      treatmentId.value = String(id)
    }
  },
)

const onSubmit = handleSubmit((values: AppointmentFormValues) => {
  emit('submit', {
    ...toAppointmentPayload(values),
    treatment_id: Number(values.treatment_id),
  })
})

defineExpose({ setErrors })
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
    <FormField label="Tratamento" :error="errors.treatment_id" html-for="appointment-treatment">
      <template #default="{ invalid }">
        <Select
          id="appointment-treatment"
          v-model="treatmentId"
          :options="treatmentOptions"
          :invalid="invalid"
          :disabled="lockTreatment || Boolean(appointment)"
          placeholder="Tratamento aberto"
        />
      </template>
    </FormField>

    <FormField label="Profissional" :error="errors.professional_user_id" html-for="appointment-pro">
      <template #default="{ invalid }">
        <Select
          id="appointment-pro"
          v-model="professionalId"
          :options="professionalOptions"
          :invalid="invalid"
          placeholder="Quem atende"
        />
      </template>
    </FormField>

    <FormField label="Data e hora" :error="errors.scheduled_at" html-for="appointment-when">
      <template #default="{ invalid }">
        <Input
          id="appointment-when"
          v-model="scheduledAt"
          v-bind="scheduledAttrs"
          type="datetime-local"
          :invalid="invalid"
        />
      </template>
    </FormField>

    <FormField
      label="Duração (minutos)"
      :hint="durationHint"
      :error="errors.duration_minutes"
      html-for="appointment-duration"
    >
      <template #default="{ invalid }">
        <Input
          id="appointment-duration"
          v-model="duration"
          v-bind="durationAttrs"
          type="number"
          :invalid="invalid"
          placeholder="Opcional"
        />
      </template>
    </FormField>

    <FormField label="Notas" :error="errors.notes" html-for="appointment-notes">
      <template #default="{ invalid }">
        <Textarea
          id="appointment-notes"
          v-model="notes"
          v-bind="notesAttrs"
          :invalid="invalid"
        />
      </template>
    </FormField>

    <div class="flex flex-wrap gap-2 pt-1">
      <Button type="submit" :loading="loading">{{ submitLabel }}</Button>
      <Button type="button" variant="ghost" :disabled="loading" @click="emit('cancel')">
        Cancelar
      </Button>
    </div>
  </form>
</template>
