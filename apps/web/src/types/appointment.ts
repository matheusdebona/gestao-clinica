import type { Client } from './client'
import type { Treatment } from './treatment'

export type AppointmentStatus = 'scheduled' | 'in_progress' | 'completed' | 'cancelled'

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
  suggested_consumptions?: unknown[] | null
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
