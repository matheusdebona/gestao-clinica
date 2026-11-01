import { api } from '@/lib/api'
import type { DataEnvelope, Paginated } from '@/types/pagination'
import type {
  CardFeeRule,
  CardFeeRulePayload,
  TaxSetting,
  TaxSettingPayload,
} from '@/types/finance'
import type { CardOperator } from '@/types/sale'

export async function listCardFeeRules(): Promise<CardFeeRule[]> {
  const rules: CardFeeRule[] = []
  let page = 1
  let last = 1
  do {
    const payload = await api<Paginated<CardFeeRule>>('/card-fee-rules', { query: { page } })
    rules.push(...payload.data)
    last = payload.meta?.last_page ?? 1
    page += 1
  } while (page <= last && page <= 20)
  return rules
}

export async function createCardFeeRule(body: CardFeeRulePayload): Promise<CardFeeRule> {
  const payload = await api<DataEnvelope<CardFeeRule>>('/card-fee-rules', { method: 'POST', body })
  return payload.data
}

export async function updateCardFeeRule(
  id: number,
  body: Partial<CardFeeRulePayload>,
): Promise<CardFeeRule> {
  const payload = await api<DataEnvelope<CardFeeRule>>(`/card-fee-rules/${id}`, {
    method: 'PUT',
    body,
  })
  return payload.data
}

export async function updateOperatorAnticipation(
  id: number,
  body: { auto_anticipate: boolean; anticipation_rate_percent: string | null },
): Promise<CardOperator> {
  const payload = await api<DataEnvelope<CardOperator>>(
    `/finance/card-operators/${id}/anticipation`,
    { method: 'PUT', body },
  )
  return payload.data
}

export async function recalculatePaymentFees(from?: string): Promise<number> {
  const payload = await api<DataEnvelope<{ payments_updated: number }>>(
    '/finance/payment-fees/recalculate',
    { method: 'POST', body: from ? { from } : {} },
  )
  return payload.data.payments_updated
}

export async function listTaxSettings(): Promise<TaxSetting[]> {
  const payload = await api<DataEnvelope<TaxSetting[]>>('/finance/tax-settings')
  return payload.data
}

export async function saveTaxSetting(body: TaxSettingPayload): Promise<TaxSetting> {
  const payload = await api<DataEnvelope<TaxSetting>>('/finance/tax-settings', {
    method: 'POST',
    body,
  })
  return payload.data
}

export async function deleteTaxSetting(id: number): Promise<void> {
  await api(`/finance/tax-settings/${id}`, { method: 'DELETE' })
}

/** "4,5" → "4.5"; vazio → null. */
export function toPercent(value: string | number | null | undefined): string | null {
  const trimmed = String(value ?? '')
    .trim()
    .replace('%', '')
    .replace(',', '.')
  return trimmed === '' ? null : trimmed
}

export function isValidPercent(value: string): boolean {
  const parsed = toPercent(value)
  if (parsed === null) return true
  const n = Number(parsed)
  return Number.isFinite(n) && n >= 0 && n <= 100
}

/** "4.5000" → "4,5" para edição. */
export function percentToInput(value: string | null | undefined): string {
  if (value === null || value === undefined || value === '') return ''
  const n = Number(value)
  return Number.isFinite(n) ? String(n).replace('.', ',') : ''
}
