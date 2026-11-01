import type { CardBrand, CardOperator, PaymentMethod } from '@/types/sale'

export interface CardFeeRule {
  id: number
  payment_method_id: number
  card_operator_id: number
  card_brand_id: number
  installments: number
  fee_percent: string | null
  fee_fixed: string | null
  is_active: boolean
  payment_method?: PaymentMethod | null
  card_operator?: CardOperator | null
  card_brand?: CardBrand | null
}

export interface CardFeeRulePayload {
  payment_method_id: number
  card_operator_id: number
  card_brand_id: number
  installments: number
  fee_percent: string | null
  fee_fixed: string | null
  is_active?: boolean
}

export type TaxRegime = 'simples' | 'presumido' | 'real' | 'mei' | 'none'

export interface TaxSetting {
  id: number
  effective_month: string
  regime: TaxRegime
  simples_rate_percent: string | null
  iss_percent: string | null
  pis_percent: string | null
  cofins_percent: string | null
  irpj_percent: string | null
  csll_percent: string | null
  other_percent: string | null
  effective_rate_percent: string
  notes: string | null
}

export interface TaxSettingPayload {
  effective_month: string
  regime: TaxRegime
  simples_rate_percent?: string | null
  iss_percent?: string | null
  pis_percent?: string | null
  cofins_percent?: string | null
  irpj_percent?: string | null
  csll_percent?: string | null
  other_percent?: string | null
  notes?: string | null
}

export interface MarginComponents {
  revenue: string
  supplies: string
  card_fees: string
  anticipation: string
  taxes: string
  commissions: string
  contribution_margin: string
  contribution_margin_percent: string | null
}

export interface MarginGroupRow extends MarginComponents {
  key: string
  label: string
  sales_count: number
}

export interface MarginSaleRow extends MarginComponents {
  sale_id: number
  client_name: string | null
  sold_at: string | null
  seller_name: string | null
  supplies_estimated: boolean
}

export interface ContributionMargin {
  from: string
  to: string
  totals: MarginComponents & {
    supplies_estimated: string
    losses: string
    losses_by_reason: { expired: string; damaged: string; other: string }
    clinic_contribution_margin: string
    clinic_contribution_margin_percent: string | null
    sales_count: number
  }
  by_protocol: MarginGroupRow[]
  by_professional: MarginGroupRow[]
  by_seller: MarginGroupRow[]
  by_origin: MarginGroupRow[]
  sales: MarginSaleRow[]
  checks: {
    tax_configured: boolean
    commission_rules: boolean
    card_payments_without_fee: number
  }
}

export type CommissionRole = 'seller' | 'professional'

export interface CommissionRuleRow {
  id: number
  role: CommissionRole
  user_id: number | null
  user_name: string | null
  protocol_id: number | null
  protocol_name: string | null
  calc_type: 'percent' | 'fixed'
  value: string
  base: 'gross' | 'net'
  is_active: boolean
  notes: string | null
}

export interface CommissionRulePayload {
  role: CommissionRole
  user_id: number | null
  protocol_id: number | null
  calc_type: 'percent' | 'fixed'
  value: string
  base: 'gross' | 'net'
  is_active?: boolean
}

export interface CommissionRulesResponse {
  data: CommissionRuleRow[]
  meta: {
    users: { id: number; name: string; roles: string[] }[]
    protocols: { id: number; name: string }[]
  }
}

/** Origem do lançamento: comissão de venda, de sessão ou remuneração por meta. */
export type CommissionOrigin = 'sales' | 'sessions' | 'goals'

export type GoalMetric = 'appointments' | 'approached' | 'sales'

export type CommissionByOrigin = Record<CommissionOrigin, string>

export interface CommissionEntryRow {
  id: number
  user_id: number
  user_name: string | null
  role: CommissionRole | 'goal'
  origin: CommissionOrigin
  goal_metric: GoalMetric | null
  goal_kind: 'occurrence' | 'bonus' | null
  lead_id: number | null
  sale_id: number | null
  appointment_id: number | null
  client_name: string | null
  occurred_at: string | null
  protocol_name: string | null
  base_amount: string
  rate_percent: string | null
  amount: string
  status: 'pending' | 'paid'
  paid_at: string | null
}

export interface CommissionEntriesResponse {
  data: CommissionEntryRow[]
  meta: {
    month: string
    total: string
    pending: string
    by_origin: CommissionByOrigin
    by_user: {
      user_id: number
      user_name: string | null
      total: string
      pending: string
      paid: string
      count: number
      by_origin: CommissionByOrigin
    }[]
  }
}

export interface GoalRemunerationRow {
  metric: GoalMetric
  label: string
  unit_label: string | null
  supports_per_occurrence: boolean
  per_occurrence_amount: string
  target_bonus_amount: string
}

export interface GoalRemunerationPayload {
  per_occurrence_amount?: string
  target_bonus_amount: string
}

export type ExpenseKind =
  'fixed' | 'variable' | 'marketing' | 'personnel' | 'taxes' | 'financial' | 'depreciation'

export interface ExpenseCategory {
  id: number
  name: string
  kind: ExpenseKind
  is_system: boolean
  is_active: boolean
}

export interface ExpenseCategoriesResponse {
  data: ExpenseCategory[]
  meta: {
    origins: { id: number; name: string }[]
    campaigns: { id: number; name: string; client_origin_id: number | null }[]
  }
}

export interface Expense {
  id: number
  expense_category_id: number
  category_name: string | null
  kind: ExpenseKind | null
  description: string
  amount: string
  competence_month: string
  due_on: string | null
  paid_on: string | null
  supplier: string | null
  recurring_expense_id: number | null
  equipment_asset_id: number | null
  client_origin_id: number | null
  client_origin_name: string | null
  campaign_id: number | null
  campaign_name: string | null
  notes: string | null
}

export interface ExpensePayload {
  expense_category_id: number
  description: string
  amount: string
  competence_month: string
  due_on: string | null
  paid_on: string | null
  supplier: string | null
  client_origin_id: number | null
  campaign_id: number | null
  notes: string | null
}

export interface ExpensesResponse {
  data: Expense[]
  meta: {
    month: string
    total: string
    paid: string
    by_kind: Record<ExpenseKind, string>
  }
}

export interface RecurringExpense {
  id: number
  expense_category_id: number
  category_name: string | null
  kind: ExpenseKind | null
  description: string
  amount: string
  due_day: number | null
  starts_month: string
  ends_month: string | null
  supplier: string | null
  client_origin_id: number | null
  client_origin_name: string | null
  campaign_id: number | null
  campaign_name: string | null
  is_active: boolean
}

export interface RecurringExpensePayload {
  expense_category_id: number
  description: string
  amount: string
  due_day: number | null
  starts_month: string
  ends_month: string | null
  supplier: string | null
  client_origin_id: number | null
  campaign_id: number | null
  is_active: boolean
}

export interface EquipmentAsset {
  id: number
  expense_category_id: number
  category_name: string | null
  name: string
  purchase_value: string
  residual_value: string
  purchased_on: string | null
  useful_life_months: number
  depreciation_start_month: string
  depreciation_end_month: string
  monthly_depreciation: string
  months_elapsed: number
  book_value: string
  is_active: boolean
  notes: string | null
}

export interface EquipmentPayload {
  name: string
  purchase_value: string
  residual_value: string | null
  purchased_on: string | null
  useful_life_months: number
  depreciation_start_month: string
  is_active: boolean
  notes: string | null
}

export type DreLineKey =
  | 'gross_revenue'
  | 'discounts'
  | 'net_revenue'
  | 'supplies'
  | 'card_fees'
  | 'anticipation'
  | 'taxes'
  | 'commissions'
  | 'losses'
  | 'other_variable'
  | 'variable_costs'
  | 'contribution_margin'
  | 'fixed'
  | 'personnel'
  | 'marketing'
  | 'expense_taxes'
  | 'financial'
  | 'depreciation'
  | 'operating_expenses'
  | 'net_result'

export type DreBreakdownKey =
  | 'fixed'
  | 'personnel'
  | 'marketing'
  | 'expense_taxes'
  | 'financial'
  | 'depreciation'
  | 'other_variable'

export interface DrePeriod {
  lines: Record<DreLineKey, string>
  breakdown: Record<DreBreakdownKey, { name: string; amount: string }[]>
  contribution_margin_percent: string | null
  net_margin_percent: string | null
  break_even_revenue: string | null
  break_even_gap: string | null
  supplies_estimated: string
  sales_count: number
  expenses_count: number
}

export interface DreReport {
  month: string
  previous_month: string
  current: DrePeriod
  previous: DrePeriod
  checks: { tax_configured: boolean }
}
