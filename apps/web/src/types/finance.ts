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
