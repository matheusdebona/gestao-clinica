import type { Client } from './client'

export type BudgetStatus =
  | 'draft'
  | 'sent'
  | 'accepted'
  | 'rejected'
  | 'expired'
  | 'superseded'

export interface BudgetItem {
  id: number
  product_id: number
  source_protocol_id: number | null
  product_name: string
  quantity: string
  list_unit_price: string
  list_line_total: string
  unit_price: string
  line_total: string
  unit_cost: string
  min_unit_price: string
}

export interface Budget {
  id: number
  clinic_id: number
  sale_id: number
  client_id: number
  created_by_user_id: number
  version: number
  status: BudgetStatus
  expected_amount: string
  effective_amount: string
  min_amount: string
  notes: string | null
  valid_until: string | null
  sent_at: string | null
  accepted_at: string | null
  rejected_at: string | null
  items?: BudgetItem[]
  client?: Client | null
  created_at: string
  updated_at: string
}

export interface BudgetCreatePayload {
  notes?: string | null
  valid_until?: string | null
}

export interface DocumentRecord {
  id: number
  clinic_id: number
  client_id: number | null
  budget_id: number | null
  sale_id: number | null
  type: string
  status: string
  filename: string
  mime_type: string
  created_at: string
}

export interface Treatment {
  id: number
  clinic_id: number
  sale_id: number
  client_id: number
  status: string
  notes: string | null
}
