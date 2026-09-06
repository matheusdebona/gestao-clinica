import { api } from '@/lib/api'
import type { DataEnvelope, Paginated } from '@/types/pagination'
import type { Treatment, TreatmentFulfillment } from '@/types/treatment'

export async function startTreatment(saleId: number): Promise<Treatment> {
  const payload = await api<DataEnvelope<Treatment>>(`/sales/${saleId}/treatments`, {
    method: 'POST',
    body: {},
  })
  return payload.data
}

export interface TreatmentListParams {
  q?: string
  status?: string
  client_id?: number
  sale_id?: number
  page?: number
  per_page?: number
}

export async function listTreatments(
  params: TreatmentListParams = {},
): Promise<Paginated<Treatment>> {
  const query: Record<string, string | number> = {
    page: params.page ?? 1,
    per_page: params.per_page ?? 20,
  }
  if (params.q) {
    query.q = params.q
  }
  if (params.status) {
    query.status = params.status
  }
  if (params.client_id) {
    query.client_id = params.client_id
  }
  if (params.sale_id) {
    query.sale_id = params.sale_id
  }

  return api<Paginated<Treatment>>('/treatments', { query })
}

export async function getTreatment(id: number): Promise<Treatment> {
  const payload = await api<DataEnvelope<Treatment>>(`/treatments/${id}`)
  return payload.data
}

export async function getTreatmentFulfillment(id: number): Promise<TreatmentFulfillment> {
  const payload = await api<DataEnvelope<TreatmentFulfillment>>(`/treatments/${id}/fulfillment`)
  return payload.data
}

export async function completeTreatment(id: number): Promise<Treatment> {
  const payload = await api<DataEnvelope<Treatment>>(`/treatments/${id}/complete`, {
    method: 'POST',
    body: {},
  })
  return payload.data
}

export async function cancelTreatment(id: number): Promise<Treatment> {
  const payload = await api<DataEnvelope<Treatment>>(`/treatments/${id}/cancel`, {
    method: 'POST',
    body: {},
  })
  return payload.data
}
