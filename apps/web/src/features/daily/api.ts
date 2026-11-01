import { api } from '@/lib/api'
import type { LeadFunnel } from '@/features/leads/funnel'
import type { DataEnvelope } from '@/types/pagination'
import type { LeadStatusColor } from '@/types/lead'
import type { PatientReturn } from '@/features/returns/api'

export interface DailyKpis {
  registered: number
  touched: number
  approached: number
  replied: number
  booked: number
  converted: number
  cancelled: number
}

export type DailyKpiKey = keyof DailyKpis

export interface DailyQueueItem {
  id: number
  name: string
  phone: string
  client_id: number | null
  cancelled_at: string | null
  lead_status_id: number
  status: { id: number; name: string; color: LeadStatusColor; system_key: string | null } | null
  origin: string | null
  last_activity_at: string | null
  days_without_contact: number
  tier: { days: number; label: string; color: LeadStatusColor } | null
  approached_at: string | null
  replied_at: string | null
  reason: 'not_approached' | 'stale'
}

export interface DailyCheckin {
  id: number
  day: string
  user: { id: number; name: string } | null
  worked: string | null
  change: string | null
  notes: string | null
  updated_at: string
}

export interface DailyTeamRow extends DailyKpis {
  user: { id: number; name: string }
  pending_follow_ups: number
  checkins: number
  checkin_done: boolean | null
  checkin: { day: string; worked: string | null; change: string | null; notes: string | null } | null
}

export interface DailyAnalysis {
  from: string
  to: string
  days: number
  today: string
  team: boolean
  viewer_id: number
  kpis: DailyKpis
  previous: { from: string; to: string; kpis: DailyKpis }
  average_7d: { from: string; to: string; kpis: Record<DailyKpiKey, string> }
  registered_by_origin: { id: number | null; label: string; total: number }[]
  status_snapshot: { id: number; label: string; color: LeadStatusColor; total: number }[]
  funnel: LeadFunnel
  follow_up: {
    count: number
    leads: DailyQueueItem[]
    /** Retornos de pacientes a vencer/atrasados; null sem sales.view. */
    returns?: { count: number; overdue: number; items: PatientReturn[] } | null
  }
  suggestions: { kind: 'warning' | 'info' | 'success'; text: string }[]
  team_rows?: DailyTeamRow[]
  users: { id: number; name: string }[]
  filters: { user_ids: number[]; origin_ids: number[]; status_ids: number[]; stale_days: number | null }
}

export interface DailyParams {
  from: string
  to: string
  user_ids?: number[]
  origin_ids?: number[]
  status_ids?: number[]
  stale_days?: number
}

function query(params: Partial<DailyParams>): Record<string, string | number> {
  const out: Record<string, string | number> = {}
  if (params.from) out.from = params.from
  if (params.to) out.to = params.to
  if (params.user_ids?.length) out.user_ids = params.user_ids.join(',')
  if (params.origin_ids?.length) out.origin_ids = params.origin_ids.join(',')
  if (params.status_ids?.length) out.status_ids = params.status_ids.join(',')
  if (params.stale_days) out.stale_days = params.stale_days
  return out
}

export async function getDailyAnalysis(params: DailyParams): Promise<DailyAnalysis> {
  const payload = await api<DataEnvelope<DailyAnalysis>>('/leads/daily-analysis', { query: query(params) })
  return payload.data
}

export async function listCheckins(params: Partial<DailyParams>): Promise<DailyCheckin[]> {
  const payload = await api<DataEnvelope<DailyCheckin[]>>('/leads/checkins', { query: query(params) })
  return payload.data
}

export async function getCheckin(day: string): Promise<{ data: DailyCheckin | null; editable: boolean }> {
  return api<{ data: DailyCheckin | null; editable: boolean }>(`/leads/checkins/${day}`)
}

export async function saveCheckin(
  day: string,
  body: { worked: string | null; change: string | null; notes: string | null },
): Promise<DailyCheckin> {
  const payload = await api<DataEnvelope<DailyCheckin>>(`/leads/checkins/${day}`, { method: 'PUT', body })
  return payload.data
}
