import { api } from '@/lib/api'
import type { Campaign, Client, ClientOrigin, ClientPayload } from '@/types/client'
import type { DataEnvelope, Paginated } from '@/types/pagination'

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

export async function listClientOrigins(): Promise<ClientOrigin[]> {
  const payload = await api<Paginated<ClientOrigin>>('/client-origins', {
    query: { active_only: 1, page: 1 },
  })
  return payload.data
}

export async function listCampaigns(clientOriginId?: number | null): Promise<Campaign[]> {
  const query: Record<string, string | number> = { active_only: 1, page: 1 }
  if (clientOriginId) {
    query.client_origin_id = clientOriginId
  }
  const payload = await api<Paginated<Campaign>>('/campaigns', { query })
  return payload.data
}
