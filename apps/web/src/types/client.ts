import type { PatientTierRef } from '@/features/clients/tiers'
import type { Lead } from './lead'

export interface ClientOrigin {
  id: number
  clinic_id: number
  name: string
  is_active: boolean
  created_at?: string
  updated_at?: string
}

export interface Campaign {
  id: number
  clinic_id: number
  client_origin_id: number
  name: string
  is_active: boolean
  client_origin?: ClientOrigin | null
  created_at?: string
  updated_at?: string
}

export interface ClientOriginPayload {
  name: string
  is_active?: boolean
}

export interface CampaignPayload {
  client_origin_id: number
  name: string
  is_active?: boolean
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
  lead_id?: number | null
  initial_consultation_amount: string | null
  is_active: boolean
  /** Faixa pelo total comprado em 12 meses (lista e detalhe). null = sem faixa. */
  tier?: PatientTierRef | null
  /** Total das vendas confirmadas nos últimos 12 meses (só com permissão de vendas). */
  purchases_12m?: string
  client_origin?: ClientOrigin | null
  campaign?: Campaign | null
  /** Lead this patient came from (history kept on conversion). */
  lead?: Lead | null
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
