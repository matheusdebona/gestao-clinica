import { BUDGET_STATUS_LABELS } from '@/features/sales/labels'
import { APPOINTMENT_STATUS_LABELS } from '@/features/appointments/labels'
import type { AcquisitionGroupBy, MarginMode, MetricsGranularity } from '@/types/metrics'

export const PERIOD_ITEMS = [
  { value: '7d', label: '7d' },
  { value: '30d', label: '30d' },
  { value: 'month', label: 'Mês' },
  { value: 'custom', label: 'Datas' },
]

export const GROUP_BY_ITEMS = [
  { value: 'origin' satisfies AcquisitionGroupBy, label: 'Origem' },
  { value: 'campaign' satisfies AcquisitionGroupBy, label: 'Campanha' },
]

export const MARGIN_MODE_ITEMS = [
  { value: 'period' satisfies MarginMode, label: 'Período' },
  { value: 'cohort_sale' satisfies MarginMode, label: 'Venda' },
]

export const GRANULARITY_LABELS: Record<MetricsGranularity, string> = {
  day: 'por dia',
  week: 'por semana',
  month: 'por mês',
}

export const PAYMENT_KIND_LABELS: Record<string, string> = {
  cash: 'Dinheiro',
  pix: 'PIX',
  check: 'Cheque',
  credit_card: 'Crédito',
  debit_card: 'Débito',
  boleto: 'Boleto',
  other: 'Outro',
}

export const SESSION_STATUS_ORDER = ['scheduled', 'in_progress', 'completed', 'cancelled'] as const

export function budgetStatusLabel(status: string): string {
  return BUDGET_STATUS_LABELS[status as keyof typeof BUDGET_STATUS_LABELS] ?? status
}

export function sessionStatusLabel(status: string): string {
  return APPOINTMENT_STATUS_LABELS[status as keyof typeof APPOINTMENT_STATUS_LABELS] ?? status
}

export function paymentKindLabel(kind: string): string {
  return PAYMENT_KIND_LABELS[kind] ?? kind
}
