import type { AppointmentConfirmationStatus, AppointmentStatus } from '@/types/appointment'

export const APPOINTMENT_STATUS_LABELS: Record<AppointmentStatus, string> = {
  scheduled: 'Agendada',
  in_progress: 'Em atendimento',
  completed: 'Concluída',
  cancelled: 'Cancelada',
}

export const APPOINTMENT_STATUS_BADGE: Record<
  AppointmentStatus,
  'success' | 'purple' | 'muted' | 'warning' | 'danger'
> = {
  scheduled: 'purple',
  in_progress: 'warning',
  completed: 'success',
  cancelled: 'muted',
}

export const CONFIRMATION_STATUS_LABELS: Record<AppointmentConfirmationStatus, string> = {
  pending: 'Aguardando confirmação',
  confirmed: 'Confirmado',
  not_confirmed: 'Não confirmado',
  declined: 'Desmarcou',
}

export const CONFIRMATION_STATUS_BADGE: Record<
  AppointmentConfirmationStatus,
  'success' | 'purple' | 'muted' | 'warning' | 'danger'
> = {
  pending: 'purple',
  confirmed: 'success',
  not_confirmed: 'warning',
  declined: 'danger',
}
