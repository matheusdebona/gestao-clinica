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

export interface CommissionEntryRow {
  id: number
  user_id: number
  user_name: string | null
  role: CommissionRole
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
    by_user: {
      user_id: number
      user_name: string | null
      total: string
      pending: string
      paid: string
      count: number
    }[]
  }
}
