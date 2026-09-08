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

export function salePaymentSummary(payment: {
  payment_method?: { name: string } | null
  card_operator?: { name: string } | null
  card_brand?: { name: string } | null
  installments?: number | string | null
}): string {
  const parts = [payment.payment_method?.name ?? 'Pagamento']
  if (payment.card_operator?.name) {
    parts.push(payment.card_operator.name)
  }
  if (payment.card_brand?.name) {
    parts.push(payment.card_brand.name)
  }
  if (payment.installments) {
    parts.push(`${payment.installments}x`)
  }
  return parts.join(' · ')
}

export const SALE_WIZARD_STEPS = [
  { id: 'client', label: 'Paciente' },
  { id: 'items', label: 'Itens' },
  { id: 'values', label: 'Valores' },
  { id: 'payments', label: 'Pagamentos' },
  { id: 'review', label: 'Revisar' },
  { id: 'budget', label: 'Orçamento' },
] as const

export const SALE_WIZARD_STEP = {
  client: 0,
  items: 1,
  values: 2,
  payments: 3,
  review: 4,
  budget: 5,
} as const

export const SALE_WIZARD_LAST_INDEX = SALE_WIZARD_STEPS.length - 1
