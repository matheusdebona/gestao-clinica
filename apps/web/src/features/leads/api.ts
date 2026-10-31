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
