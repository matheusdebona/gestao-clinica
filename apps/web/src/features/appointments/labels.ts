import type { AppointmentStatus } from '@/types/appointment'

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
