import type { TreatmentStatus } from '@/types/treatment'

export const TREATMENT_STATUS_LABELS: Record<TreatmentStatus, string> = {
  open: 'Aberto',
  completed: 'Concluído',
  cancelled: 'Cancelado',
}

export const TREATMENT_STATUS_BADGE: Record<TreatmentStatus, 'purple' | 'success' | 'muted'> = {
  open: 'purple',
  completed: 'success',
  cancelled: 'muted',
}
