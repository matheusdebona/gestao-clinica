import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { emptyToInt, emptyToNull } from '@/lib/formatters'
import type { Appointment, AppointmentPayload } from '@/types/appointment'

export const appointmentFormSchema = toTypedSchema(
  z.object({
    treatment_id: z.string().trim().min(1, 'Selecione o tratamento.'),
    professional_user_id: z.string().trim().min(1, 'Selecione o profissional.'),
    scheduled_at: z.string().trim().min(1, 'Informe data e hora.'),
    duration_minutes: z.string().refine((value) => {
      if (!value.trim()) {
        return true
      }
      const parsed = Number(value)
      return Number.isInteger(parsed) && parsed >= 1 && parsed <= 1440
    }, 'Informe minutos entre 1 e 1440.'),
    notes: z.string(),
  }),
)

export type AppointmentFormValues = {
  treatment_id: string
  professional_user_id: string
  scheduled_at: string
  duration_minutes: string
  notes: string
}

export const emptyAppointmentForm = (): AppointmentFormValues => ({
  treatment_id: '',
  professional_user_id: '',
  scheduled_at: '',
  duration_minutes: '',
  notes: '',
})

export function datetimeLocalToIso(value: string): string {
  const parsed = new Date(value)
  return parsed.toISOString()
}

export function isoToDatetimeLocal(value: string | null | undefined): string {
  if (!value) {
    return ''
  }
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) {
    return ''
  }
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${parsed.getFullYear()}-${pad(parsed.getMonth() + 1)}-${pad(parsed.getDate())}T${pad(parsed.getHours())}:${pad(parsed.getMinutes())}`
}

export function toAppointmentPayload(values: AppointmentFormValues): AppointmentPayload {
  const duration = emptyToInt(values.duration_minutes)
  return {
    professional_user_id: Number(values.professional_user_id),
    scheduled_at: datetimeLocalToIso(values.scheduled_at),
    duration_minutes: duration,
    notes: emptyToNull(values.notes),
  }
}

export function appointmentToForm(appointment: Appointment): AppointmentFormValues {
  return {
    treatment_id: String(appointment.treatment_id),
    professional_user_id: appointment.professional_user_id
      ? String(appointment.professional_user_id)
      : '',
    scheduled_at: isoToDatetimeLocal(appointment.scheduled_at),
    duration_minutes: appointment.duration_minutes ? String(appointment.duration_minutes) : '',
    notes: appointment.notes ?? '',
  }
}
