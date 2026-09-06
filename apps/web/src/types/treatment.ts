import type { Client } from './client'

export type TreatmentStatus = 'open' | 'completed' | 'cancelled'

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
  created_at?: string
  updated_at?: string
}
