import { api } from '@/lib/api'
import type { DataEnvelope, Paginated } from '@/types/pagination'
import type {
  CardFeeRule,
  DreReport,
  CardFeeRulePayload,
  CommissionEntriesResponse,
  CommissionRulePayload,
  CommissionRuleRow,
  CommissionRulesResponse,
  GoalRemunerationPayload,
  GoalRemunerationRow,
  ContributionMargin,
  EquipmentAsset,
  EquipmentPayload,
  Expense,
  ExpenseCategoriesResponse,
  ExpenseCategory,
  ExpenseKind,
  ExpensePayload,
  ExpensesResponse,
  RecurringExpense,
  RecurringExpensePayload,
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

export async function getContributionMargin(params: {
  from: string
  to: string
}): Promise<ContributionMargin> {
  const payload = await api<DataEnvelope<ContributionMargin>>('/metrics/contribution-margin', {
    query: params,
  })
  return payload.data
}

export function listCommissionRules(): Promise<CommissionRulesResponse> {
  return api<CommissionRulesResponse>('/finance/commission-rules')
}

export async function saveCommissionRule(
  body: CommissionRulePayload,
  id?: number,
): Promise<CommissionRuleRow> {
  const payload = await api<DataEnvelope<CommissionRuleRow>>(
    id ? `/finance/commission-rules/${id}` : '/finance/commission-rules',
    { method: id ? 'PUT' : 'POST', body },
  )
  return payload.data
}

export async function setCommissionRuleActive(id: number, active: boolean): Promise<void> {
  await api(`/finance/commission-rules/${id}`, { method: 'PUT', body: { is_active: active } })
}

export function listCommissions(month: string): Promise<CommissionEntriesResponse> {
  return api<CommissionEntriesResponse>('/finance/commissions', { query: { month } })
}

export async function rebuildCommissions(month: string): Promise<number> {
  const payload = await api<DataEnvelope<{ processed: number }>>('/finance/commissions/rebuild', {
    method: 'POST',
    body: { month },
  })
  return payload.data.processed
}

export async function markCommissionsPaid(ids: number[], paid: boolean): Promise<number> {
  const payload = await api<DataEnvelope<{ updated: number }>>('/finance/commissions/mark-paid', {
    method: 'POST',
    body: { ids, paid },
  })
  return payload.data.updated
}

export function listExpenseCategories(): Promise<ExpenseCategoriesResponse> {
  return api<ExpenseCategoriesResponse>('/finance/expense-categories')
}

export async function saveExpenseCategory(
  body: { name?: string; kind?: ExpenseKind; is_active?: boolean },
  id?: number,
): Promise<ExpenseCategory> {
  const payload = await api<DataEnvelope<ExpenseCategory>>(
    id ? `/finance/expense-categories/${id}` : '/finance/expense-categories',
    { method: id ? 'PUT' : 'POST', body },
  )
  return payload.data
}

export function listExpenses(month: string): Promise<ExpensesResponse> {
  return api<ExpensesResponse>('/finance/expenses', { query: { month } })
}

export async function saveExpense(body: ExpensePayload, id?: number): Promise<Expense> {
  const payload = await api<DataEnvelope<Expense>>(
    id ? `/finance/expenses/${id}` : '/finance/expenses',
    { method: id ? 'PUT' : 'POST', body },
  )
  return payload.data
}

export async function deleteExpense(id: number): Promise<void> {
  await api(`/finance/expenses/${id}`, { method: 'DELETE' })
}

export async function listRecurringExpenses(): Promise<RecurringExpense[]> {
  const payload = await api<DataEnvelope<RecurringExpense[]>>('/finance/recurring-expenses')
  return payload.data
}

export async function saveRecurringExpense(
  body: Partial<RecurringExpensePayload>,
  id?: number,
): Promise<RecurringExpense> {
  const payload = await api<DataEnvelope<RecurringExpense>>(
    id ? `/finance/recurring-expenses/${id}` : '/finance/recurring-expenses',
    { method: id ? 'PUT' : 'POST', body },
  )
  return payload.data
}

export async function listEquipment(): Promise<EquipmentAsset[]> {
  const payload = await api<DataEnvelope<EquipmentAsset[]>>('/finance/equipment')
  return payload.data
}

export async function saveEquipment(
  body: Partial<EquipmentPayload>,
  id?: number,
): Promise<EquipmentAsset> {
  const payload = await api<DataEnvelope<EquipmentAsset>>(
    id ? `/finance/equipment/${id}` : '/finance/equipment',
    { method: id ? 'PUT' : 'POST', body },
  )
  return payload.data
}

export function currentMonthKey(): string {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
}

export async function getDre(month: string): Promise<DreReport> {
  const payload = await api<DataEnvelope<DreReport>>(`/finance/dre?month=${month}`)
  return payload.data
}

export async function listGoalRemunerations(): Promise<GoalRemunerationRow[]> {
  const payload = await api<DataEnvelope<GoalRemunerationRow[]>>('/finance/goal-remunerations')
  return payload.data
}

export async function saveGoalRemuneration(
  metric: GoalRemunerationRow['metric'],
  body: GoalRemunerationPayload,
): Promise<GoalRemunerationRow[]> {
  const payload = await api<DataEnvelope<GoalRemunerationRow[]>>(
    `/finance/goal-remunerations/${metric}`,
    { method: 'PUT', body },
  )
  return payload.data
}
