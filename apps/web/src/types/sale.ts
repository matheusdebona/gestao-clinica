import type { Client } from './client'
import type { Product } from './product'

export type SaleStatus = 'draft' | 'confirmed' | 'cancelled'

export interface SaleProtocolReference {
  id: number
  name: string
  suggested_price: string
  min_price: string
  special_price: string | null
}

export interface SaleItem {
  id: number
  product_id: number
  source_protocol_id: number | null
  product_name: string
  quantity: string
  list_unit_price: string
  list_line_total: string
  unit_price: string
  unit_cost: string
  min_unit_price: string
  line_total: string
  line_min_total: string
  is_below_minimum: boolean
  product?: Product | null
  created_at: string
  updated_at: string
}

export interface SalePayment {
  id: number
  payment_method_id: number
  amount: string
  card_operator_id: number | null
  card_brand_id: number | null
  installments: number | null
  paid_at: string | null
  payment_method?: PaymentMethod | null
  created_at: string
  updated_at: string
}

export interface PaymentMethod {
  id: number
  clinic_id: number
  name: string
  code: string
  kind: string
  requires_card_meta: boolean
  fee_percent: string | null
  fee_fixed: string | null
  is_active: boolean
}

export interface CardOperator {
  id: number
  clinic_id: number
  name: string
  code: string | null
  auto_anticipate: boolean
  is_active: boolean
}

export interface CardBrand {
  id: number
  clinic_id: number
  name: string
  code: string
  is_active: boolean
}

export interface Sale {
  id: number
  clinic_id: number
  client_id: number
  sold_by_user_id: number
  sold_at: string | null
  expected_amount: string
  effective_amount: string
  effective_amount_is_manual: boolean
  min_amount: string
  cost_total: string
  margin_at_effective: string
  is_below_minimum: boolean
  payments_total: string
  payments_balance: string
  status: SaleStatus
  notes: string | null
  treatment_id: number | null
  client?: Client | null
  items?: SaleItem[]
  payments?: SalePayment[]
  protocol_references?: SaleProtocolReference[]
  created_at: string
  updated_at: string
}

export interface SaleCreatePayload {
  client_id: number
  sold_at?: string | null
  notes?: string | null
}

export interface SaleUpdatePayload {
  sold_at?: string | null
  notes?: string | null
  effective_amount?: string | number
}

export interface SaleItemPayload {
  product_id: number
  quantity: number | string
  unit_price?: number | string | null
  source_protocol_id?: number | null
}

export interface SalePaymentPayload {
  payment_method_id: number
  amount: number | string
  card_operator_id?: number | null
  card_brand_id?: number | null
  installments?: number | null
  paid_at?: string | null
}
