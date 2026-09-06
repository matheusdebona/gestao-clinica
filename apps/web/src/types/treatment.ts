import type { Client } from './client'
import type { Sale } from './sale'

export type TreatmentStatus = 'open' | 'completed' | 'cancelled'

export interface TreatmentSessionSummary {
  id: number
  status: string
  scheduled_at: string | null
  started_at?: string | null
  finished_at?: string | null
  duration_minutes?: number | null
  total_cost?: string
  notes?: string | null
  professional?: { id: number; name: string } | null
}

export interface Treatment {
  id: number
  clinic_id: number
  sale_id: number
  client_id: number
  opened_by_user_id?: number | null
  status: TreatmentStatus
  total_cost?: string
  notes: string | null
  client?: Client | null
  sale?: Sale | null
  appointments?: TreatmentSessionSummary[] | null
  created_at?: string
  updated_at?: string
}

export interface TreatmentFulfillmentItem {
  sale_item_id: number
  product_id: number
  product_name: string
  sold_quantity: string
  consumed_quantity: string
  remaining_quantity: string
  stock_quantity: string
}

export interface TreatmentFulfillment {
  treatment_id: number
  sale_id: number
  items: TreatmentFulfillmentItem[]
}
