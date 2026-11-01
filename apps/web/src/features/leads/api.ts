import { api } from '@/lib/api'
import type { Client } from '@/types/client'
import type {
  Lead,
  LeadActivity,
  LeadConvertPayload,
  LeadConvertResult,
  LeadPayload,
  LeadPhoneConflict,
  LeadStatus,
  LeadStatusColor,
  LeadStatusPayload,
  LeadUserRef,
} from '@/types/lead'
import type { DataEnvelope, Paginated } from '@/types/pagination'

export interface LeadListParams {
  q?: string
  page?: number
  lead_status_ids?: number[]
  client_origin_id?: number
  created_by_user_id?: number
  converted?: boolean
  stale?: boolean
  stale_days?: number
  from?: string
  to?: string
}

export function leadListQuery(params: LeadListParams = {}): Record<string, string | number> {
  const query: Record<string, string | number> = { page: params.page ?? 1 }
  if (params.q) {
    query.q = params.q
  }
  if (params.lead_status_ids?.length) {
    query.lead_status_id = params.lead_status_ids.join(',')
  }
  if (params.client_origin_id) {
    query.client_origin_id = params.client_origin_id
  }
  if (params.created_by_user_id) {
    query.created_by_user_id = params.created_by_user_id
  }
  if (params.converted !== undefined) {
    query.converted = params.converted ? 1 : 0
  }
  if (params.stale_days) {
    query.stale_days = params.stale_days
  } else if (params.stale) {
    query.stale = 1
  }
  if (params.from) {
    query.from = params.from
  }
  if (params.to) {
    query.to = params.to
  }
  return query
}

export async function listLeads(params: LeadListParams = {}): Promise<Paginated<Lead>> {
  return api<Paginated<Lead>>('/leads', { query: leadListQuery(params) })
}

export async function getLead(id: number): Promise<Lead> {
  const payload = await api<DataEnvelope<Lead>>(`/leads/${id}`)
  return payload.data
}

export async function createLead(body: LeadPayload): Promise<Lead> {
  const payload = await api<DataEnvelope<Lead>>('/leads', { method: 'POST', body })
  return payload.data
}

export async function updateLead(id: number, body: Partial<LeadPayload>): Promise<Lead> {
  const payload = await api<DataEnvelope<Lead>>(`/leads/${id}`, { method: 'PUT', body })
  return payload.data
}

export async function changeLeadStatus(
  id: number,
  body: { lead_status_id: number; note?: string | null },
): Promise<Lead> {
  const payload = await api<DataEnvelope<Lead>>(`/leads/${id}/status`, { method: 'PATCH', body })
  return payload.data
}

/** Final: the lead stays in the list, history and metrics. */
export async function cancelLead(id: number, body: { reason?: string | null }): Promise<Lead> {
  const payload = await api<DataEnvelope<Lead>>(`/leads/${id}/cancel`, { method: 'POST', body })
  return payload.data
}

export async function addLeadComment(id: number, body: { body: string }): Promise<LeadActivity> {
  const payload = await api<DataEnvelope<LeadActivity>>(`/leads/${id}/comments`, {
    method: 'POST',
    body,
  })
  return payload.data
}

/** Only the latest comment, by its author or an admin (403 otherwise). */
export async function updateLeadComment(
  leadId: number,
  commentId: number,
  body: { body: string },
): Promise<LeadActivity> {
  const payload = await api<DataEnvelope<LeadActivity>>(`/leads/${leadId}/comments/${commentId}`, {
    method: 'PATCH',
    body,
  })
  return payload.data
}

export async function checkLeadPhone(
  phone: string,
  leadId?: number | null,
): Promise<LeadPhoneConflict | null> {
  const query: Record<string, string | number> = { phone }
  if (leadId) {
    query.lead_id = leadId
  }
  const payload = await api<{ data: LeadPhoneConflict | null }>('/leads/phone-check', { query })
  return payload.data
}

export async function listLeadMatchingClients(id: number): Promise<Client[]> {
  const payload = await api<{ data: Client[] }>(`/leads/${id}/matching-clients`)
  return payload.data
}

export async function convertLead(id: number, body: LeadConvertPayload): Promise<LeadConvertResult> {
  const payload = await api<DataEnvelope<LeadConvertResult>>(`/leads/${id}/convert`, {
    method: 'POST',
    body,
  })
  return payload.data
}

export async function listLeadCreators(): Promise<LeadUserRef[]> {
  const payload = await api<{ data: LeadUserRef[] }>('/leads/creators')
  return payload.data
}

export async function listLeadStatuses(params: { active_only?: boolean } = {}): Promise<LeadStatus[]> {
  const payload = await api<{ data: LeadStatus[] }>('/lead-statuses', {
    query: params.active_only ? { active_only: 1 } : {},
  })
  return payload.data
}

export async function createLeadStatus(body: LeadStatusPayload): Promise<LeadStatus> {
  const payload = await api<DataEnvelope<LeadStatus>>('/lead-statuses', { method: 'POST', body })
  return payload.data
}

export async function updateLeadStatus(
  id: number,
  body: Partial<LeadStatusPayload>,
): Promise<LeadStatus> {
  const payload = await api<DataEnvelope<LeadStatus>>(`/lead-statuses/${id}`, {
    method: 'PUT',
    body,
  })
  return payload.data
}

export async function deleteLeadStatus(id: number): Promise<{ deleted: boolean; message: string }> {
  return api<{ deleted: boolean; message: string }>(`/lead-statuses/${id}`, { method: 'DELETE' })
}

/** Logs a WhatsApp touch in the lead timeline (the wa.me link is opened by the UI). */
export async function logLeadWhatsApp(id: number): Promise<LeadActivity> {
  const payload = await api<DataEnvelope<LeadActivity>>(`/leads/${id}/whatsapp`, { method: 'POST' })
  return payload.data
}

export interface StaleLeadSummaryItem {
  id: number
  name: string
  phone: string | null
  status: { id: number; name: string; color: LeadStatusColor } | null
  last_activity_at: string | null
}

export interface StaleLeadsSummary {
  /** Smallest tier (null when the clinic has no follow-up alerts). */
  days: number | null
  count: number
  /** Cumulative: "7+" includes 30+ and 60+. */
  tiers: { days: number; label: string; color: LeadStatusColor; count: number }[]
  leads: StaleLeadSummaryItem[]
}

export async function getStaleLeadsSummary(): Promise<StaleLeadsSummary> {
  const payload = await api<DataEnvelope<StaleLeadsSummary>>('/leads/stale-summary')
  return payload.data
}

export interface FollowUpTier {
  id: number
  days: number
  label: string | null
  display_label: string
  color: LeadStatusColor
}

export interface FollowUpTierPayload {
  days: number
  label: string | null
  color: LeadStatusColor
}

export async function listFollowUpTiers(): Promise<FollowUpTier[]> {
  const payload = await api<DataEnvelope<FollowUpTier[]>>('/lead-follow-up-tiers')
  return payload.data
}

export async function createFollowUpTier(body: FollowUpTierPayload): Promise<FollowUpTier> {
  const payload = await api<DataEnvelope<FollowUpTier>>('/lead-follow-up-tiers', { method: 'POST', body })
  return payload.data
}

export async function updateFollowUpTier(id: number, body: FollowUpTierPayload): Promise<FollowUpTier> {
  const payload = await api<DataEnvelope<FollowUpTier>>(`/lead-follow-up-tiers/${id}`, { method: 'PUT', body })
  return payload.data
}

export async function deleteFollowUpTier(id: number): Promise<void> {
  await api(`/lead-follow-up-tiers/${id}`, { method: 'DELETE' })
}

export type LeadQuickMilestone = 'approached' | 'replied'

/** "Abordado" / "Respondeu": set once, attributed to the current user. */
export async function markLeadMilestone(id: number, milestone: LeadQuickMilestone): Promise<Lead> {
  const payload = await api<DataEnvelope<Lead>>(`/leads/${id}/milestones/${milestone}`, {
    method: 'POST',
    body: {},
  })
  return payload.data
}
