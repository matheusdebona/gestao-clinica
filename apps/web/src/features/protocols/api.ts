import { api } from '@/lib/api'
import type { DataEnvelope, Paginated } from '@/types/pagination'
import type {
  Protocol,
  ProtocolCreatePayload,
  ProtocolHeaderPayload,
  ProtocolItemPayload,
} from '@/types/protocol'

export interface ProtocolListParams {
  q?: string
  page?: number
  is_active?: boolean
}

export async function listProtocols(params: ProtocolListParams = {}): Promise<Paginated<Protocol>> {
  const query: Record<string, string | number> = {
    page: params.page ?? 1,
  }
  if (params.q) {
    query.q = params.q
  }
  if (params.is_active !== undefined) {
    query.is_active = params.is_active ? 1 : 0
  }

  return api<Paginated<Protocol>>('/protocols', { query })
}

export async function getProtocol(id: number): Promise<Protocol> {
  const payload = await api<DataEnvelope<Protocol>>(`/protocols/${id}`)
  return payload.data
}

export async function createProtocol(body: ProtocolCreatePayload): Promise<Protocol> {
  const payload = await api<DataEnvelope<Protocol>>('/protocols', { method: 'POST', body })
  return payload.data
}

export async function updateProtocol(
  id: number,
  body: Partial<ProtocolHeaderPayload>,
): Promise<Protocol> {
  const payload = await api<DataEnvelope<Protocol>>(`/protocols/${id}`, { method: 'PUT', body })
  return payload.data
}

export async function syncProtocolItems(
  id: number,
  items: ProtocolItemPayload[],
): Promise<Protocol> {
  const payload = await api<DataEnvelope<Protocol>>(`/protocols/${id}/items`, {
    method: 'PUT',
    body: { items },
  })
  return payload.data
}

export async function deactivateProtocol(id: number): Promise<void> {
  await api(`/protocols/${id}`, { method: 'DELETE' })
}
