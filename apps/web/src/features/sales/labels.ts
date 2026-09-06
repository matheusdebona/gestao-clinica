import type { BudgetStatus } from '@/types/budget'
import type { SaleStatus } from '@/types/sale'

export const SALE_STATUS_LABELS: Record<SaleStatus, string> = {
  draft: 'Rascunho',
  confirmed: 'Confirmada',
  cancelled: 'Cancelada',
}

export const SALE_STATUS_BADGE: Record<SaleStatus, 'muted' | 'success' | 'danger'> = {
  draft: 'muted',
  confirmed: 'success',
  cancelled: 'danger',
}

export const BUDGET_STATUS_LABELS: Record<BudgetStatus, string> = {
  draft: 'Rascunho',
  sent: 'Enviado',
  accepted: 'Aceito',
  rejected: 'Recusado',
  expired: 'Expirado',
  superseded: 'Substituído',
}

export const BUDGET_STATUS_BADGE: Record<
  BudgetStatus,
  'muted' | 'purple' | 'success' | 'danger' | 'warning'
> = {
  draft: 'muted',
  sent: 'purple',
  accepted: 'success',
  rejected: 'danger',
  expired: 'warning',
  superseded: 'muted',
}

export const SALE_WIZARD_STEPS = [
  { id: 'client', label: 'Cliente' },
  { id: 'items', label: 'Itens' },
  { id: 'values', label: 'Valores' },
  { id: 'payments', label: 'Pagamentos' },
  { id: 'review', label: 'Revisar' },
] as const
