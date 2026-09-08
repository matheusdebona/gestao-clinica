import { api } from '@/lib/api'
import type {
  Campaign,
  CampaignPayload,
  Client,
  ClientOrigin,
  ClientOriginPayload,
  ClientPayload,
} from '@/types/client'
import type { DataEnvelope, Paginated } from '@/types/pagination'

export interface AttributionListParams {
  page?: number
  active_only?: boolean
  client_origin_id?: number
}

function attributionQuery(params: AttributionListParams = {}): Record<string, string | number> {
  const query: Record<string, string | number> = {
    page: params.page ?? 1,
  }
  if (params.active_only) {
    query.active_only = 1
  }
  if (params.client_origin_id) {
    query.client_origin_id = params.client_origin_id
  }
  return query
}

export interface ClientListParams {
  q?: string
  page?: number
  is_active?: boolean
}

export async function listClients(params: ClientListParams = {}): Promise<Paginated<Client>> {
  const query: Record<string, string | number> = {
    page: params.page ?? 1,
  }
  if (params.q) {
    query.q = params.q
  }
  if (params.is_active !== undefined) {
    query.is_active = params.is_active ? 1 : 0
  }

  return api<Paginated<Client>>('/clients', { query })
}

export async function getClient(id: number): Promise<Client> {
  const payload = await api<DataEnvelope<Client>>(`/clients/${id}`)
  return payload.data
}

export async function createClient(body: ClientPayload): Promise<Client> {
  const payload = await api<DataEnvelope<Client>>('/clients', {
    method: 'POST',
    body,
  })
  return payload.data
}

export async function updateClient(id: number, body: Partial<ClientPayload>): Promise<Client> {
  const payload = await api<DataEnvelope<Client>>(`/clients/${id}`, {
    method: 'PUT',
    body,
  })
  return payload.data
}

export async function deactivateClient(id: number): Promise<void> {
  await api(`/clients/${id}`, { method: 'DELETE' })
}

export async function listClientOrigins(
  params: AttributionListParams = {},
): Promise<Paginated<ClientOrigin>> {
  return api<Paginated<ClientOrigin>>('/client-origins', { query: attributionQuery(params) })
}

export async function getClientOrigin(id: number): Promise<ClientOrigin> {
  const payload = await api<DataEnvelope<ClientOrigin>>(`/client-origins/${id}`)
  return payload.data
}

export async function createClientOrigin(body: ClientOriginPayload): Promise<ClientOrigin> {
  const payload = await api<DataEnvelope<ClientOrigin>>('/client-origins', {
    method: 'POST',
    body,
  })
  return payload.data
}

export async function updateClientOrigin(
  id: number,
  body: Partial<ClientOriginPayload>,
): Promise<ClientOrigin> {
  const payload = await api<DataEnvelope<ClientOrigin>>(`/client-origins/${id}`, {
    method: 'PUT',
    body,
  })
  return payload.data
}

export async function deactivateClientOrigin(id: number): Promise<void> {
  await api(`/client-origins/${id}`, { method: 'DELETE' })
}

export async function listCampaigns(params: AttributionListParams = {}): Promise<Paginated<Campaign>> {
  return api<Paginated<Campaign>>('/campaigns', { query: attributionQuery(params) })
}

export async function getCampaign(id: number): Promise<Campaign> {
  const payload = await api<DataEnvelope<Campaign>>(`/campaigns/${id}`)
  return payload.data
}

export async function createCampaign(body: CampaignPayload): Promise<Campaign> {
  const payload = await api<DataEnvelope<Campaign>>('/campaigns', {
    method: 'POST',
    body,
  })
  return payload.data
}

export async function updateCampaign(id: number, body: Partial<CampaignPayload>): Promise<Campaign> {
  const payload = await api<DataEnvelope<Campaign>>(`/campaigns/${id}`, {
    method: 'PUT',
    body,
  })
  return payload.data
}

export async function deactivateCampaign(id: number): Promise<void> {
  await api(`/campaigns/${id}`, { method: 'DELETE' })
}
