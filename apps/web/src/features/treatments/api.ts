import { api } from '@/lib/api'
import type { Treatment } from '@/types/budget'
import type { DataEnvelope } from '@/types/pagination'

export async function startTreatment(saleId: number): Promise<Treatment> {
  const payload = await api<DataEnvelope<Treatment>>(`/sales/${saleId}/treatments`, {
    method: 'POST',
    body: {},
  })
  return payload.data
}
