import { api } from '@/lib/api'
import type { DataEnvelope, Paginated } from '@/types/pagination'
import type { Treatment } from '@/types/treatment'

export async function startTreatment(saleId: number): Promise<Treatment> {
  const payload = await api<DataEnvelope<Treatment>>(`/sales/${saleId}/treatments`, {
    method: 'POST',
    body: {},
  })
  return payload.data
}

export interface TreatmentListParams {
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
    per_page: params.per_page ?? 50,
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
