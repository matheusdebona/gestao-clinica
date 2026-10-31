import type { Client, ClientOrigin } from './client'
import type { Appointment } from './appointment'

/** Design-system tones shared with `Badge`. Never raw hex. */
export type LeadStatusColor = 'muted' | 'purple' | 'success' | 'warning' | 'danger'

export interface LeadStatus {
  id: number
  clinic_id: number
  name: string
  color: LeadStatusColor
  sort_order: number
  is_active: boolean
  is_default: boolean
  system_key: string | null
  leads_count?: number
  created_at?: string
  updated_at?: string
}

export interface LeadStatusPayload {
  name: string
  color: LeadStatusColor
  sort_order?: number
  is_active?: boolean
  is_default?: boolean
}

export interface LeadUserRef {
  id: number
  name: string
}

export type LeadActivityType =
  | 'created'
  | 'status_changed'
  | 'note'
  | 'converted'
  | 'cancelled'
  | 'comment'
  | 'whatsapp'
  | 'approached'
  | 'replied'
  | 'booked'

export interface LeadActivity {
  id: number
  type: LeadActivityType
  note: string | null
  user?: LeadUserRef | null
  from_status?: LeadStatus | null
  to_status?: LeadStatus | null
  created_at: string
}

export interface Lead {
  id: number
  clinic_id: number
  name: string
  phone: string
  client_origin_id: number
  lead_status_id: number
  notes: string | null
  created_by_user_id: number | null
  client_id: number | null
  converted_at: string | null
  cancelled_at: string | null
  approached_at?: string | null
  replied_at?: string | null
  booked_at?: string | null
  approached_by?: LeadUserRef | null
  replied_by?: LeadUserRef | null
  booked_by?: LeadUserRef | null
  comments_count?: number
  last_activity_at?: string | null
  client_origin?: ClientOrigin | null
  status?: LeadStatus | null
  created_by?: LeadUserRef | null
  client?: { id: number; name: string; whatsapp: string } | null
  activities?: LeadActivity[]
  created_at: string
  updated_at: string
}

export interface LeadPayload {
  name: string
  phone: string
  client_origin_id: number
  lead_status_id?: number | null
  notes: string | null
  /** Only on creation: "Já abordei" counts as first outreach. */
  already_approached?: boolean
}

export interface LeadConvertPayload {
  scheduled_at: string
  professional_user_id: number
  duration_minutes: number | null
  notes: string | null
  client_id?: number | null
  create_new_client?: boolean
}

export interface LeadConvertResult {
  lead: Lead
  client: Client
  appointment: Appointment
  client_created: boolean
}

export interface LeadPhoneConflict {
  type: 'lead' | 'patient'
  message: string
  lead: {
    id: number
    name: string
    status: Pick<LeadStatus, 'id' | 'name' | 'color' | 'system_key'> | null
    created_by: LeadUserRef | null
    created_at: string | null
  } | null
  client: { id: number; name: string; is_active: boolean } | null
}
