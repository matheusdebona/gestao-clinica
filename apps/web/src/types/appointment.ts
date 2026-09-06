import type { Client } from './client'
import type { Product } from './product'
import type { SalePayment } from './sale'
import type { Treatment } from './treatment'

export type AppointmentStatus = 'scheduled' | 'in_progress' | 'completed' | 'cancelled'

export type ConsumptionSource = 'suggested' | 'extra'

export interface AppointmentProfessional {
  id: number
  name: string
  email?: string
}

export interface AppointmentTreatment {
  id: number
  status: string
  sale_id: number
  client_id: number
}

export interface StockWarning {
  product_id: number
  product_name: string
  suggested_quantity: string
  stock_quantity: string
}

export interface SuggestedConsumption {
  sale_item_id: number
  product_id: number
  product_name: string
  quantity: string
  stock_quantity: string
}

export interface AppointmentConsumption {
  id: number
  appointment_id: number
  product_id: number
  sale_item_id: number | null
  source: ConsumptionSource
  quantity: string
  is_complimentary: boolean
  charged_amount: string
  sale_payment_id: number | null
  unit_cost: string | null
  line_cost: string | null
  product?: Product | null
  sale_payment?: SalePayment | null
  created_at?: string
  updated_at?: string
}

export interface Appointment {
  id: number
  clinic_id: number
  treatment_id: number
  client_id: number
  professional_user_id: number | null
  status: AppointmentStatus
  scheduled_at: string | null
  started_at: string | null
  finished_at: string | null
  duration_minutes: number | null
  total_cost: string
  total_charged_on_appointment: string
  stock_warning: StockWarning[] | string[] | null
  notes: string | null
  client?: Client | null
  professional?: AppointmentProfessional | null
  treatment?: AppointmentTreatment | Treatment | null
  consumptions?: AppointmentConsumption[] | null
  suggested_consumptions?: SuggestedConsumption[] | null
  stock_warnings?: StockWarning[] | null
  warnings?: string[] | null
  created_at: string
  updated_at: string
}

export interface AppointmentPayload {
  professional_user_id: number
  scheduled_at: string
  duration_minutes?: number | null
  notes?: string | null
}

export interface ConsumptionPaymentPayload {
  payment_method_id: number
  card_operator_id?: number | null
  card_brand_id?: number | null
  installments?: number | null
  paid_at?: string | null
}

export interface ConsumptionPayload {
  source: ConsumptionSource
  product_id: number
  quantity: number
  sale_item_id?: number | null
  is_complimentary?: boolean
  charged_amount?: number | string
  payment?: ConsumptionPaymentPayload
}
