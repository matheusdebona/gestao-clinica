export interface ClientOrigin {
  id: number
  clinic_id: number
  name: string
  is_active: boolean
}

export interface Campaign {
  id: number
  clinic_id: number
  client_origin_id: number
  name: string
  is_active: boolean
  client_origin?: ClientOrigin | null
}

export interface Client {
  id: number
  clinic_id: number
  name: string
  whatsapp: string
  notes: string | null
  main_pains: string | null
  service_duration_minutes: number | null
  client_origin_id: number | null
  campaign_id: number | null
  initial_consultation_amount: string | null
  is_active: boolean
  client_origin?: ClientOrigin | null
  campaign?: Campaign | null
  created_at: string
  updated_at: string
}

export interface ClientPayload {
  name: string
  whatsapp: string
  notes: string | null
  main_pains: string | null
  service_duration_minutes: number | null
  client_origin_id: number | null
  campaign_id: number | null
  initial_consultation_amount: string | null
  is_active?: boolean
}
