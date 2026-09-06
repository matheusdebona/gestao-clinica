import { api } from '@/lib/api'
import type { DataEnvelope, Paginated } from '@/types/pagination'
import type {
  Sale,
  SaleCreatePayload,
  SaleItemPayload,
  SalePaymentPayload,
  SaleStatus,
  SaleUpdatePayload,
} from '@/types/sale'

export interface SaleListParams {
  q?: string
  page?: number
  status?: SaleStatus | ''
  client_id?: number
}

export async function listSales(params: SaleListParams = {}): Promise<Paginated<Sale>> {
  const query: Record<string, string | number> = {
    page: params.page ?? 1,
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

  return api<Paginated<Sale>>('/sales', { query })
}

export async function getSale(id: number): Promise<Sale> {
  const payload = await api<DataEnvelope<Sale>>(`/sales/${id}`)
  return payload.data
}

export async function createSale(body: SaleCreatePayload): Promise<Sale> {
  const payload = await api<DataEnvelope<Sale>>('/sales', { method: 'POST', body })
  return payload.data
}

export async function updateSale(id: number, body: SaleUpdatePayload): Promise<Sale> {
  const payload = await api<DataEnvelope<Sale>>(`/sales/${id}`, { method: 'PATCH', body })
  return payload.data
}

export async function syncSaleItems(id: number, items: SaleItemPayload[]): Promise<Sale> {
  const payload = await api<DataEnvelope<Sale>>(`/sales/${id}/items`, {
    method: 'PUT',
    body: { items },
  })
  return payload.data
}

export async function applyProtocolToSale(id: number, protocolId: number): Promise<Sale> {
  const payload = await api<DataEnvelope<Sale>>(`/sales/${id}/apply-protocol`, {
    method: 'POST',
    body: { protocol_id: protocolId },
  })
  return payload.data
}

export async function syncSalePayments(id: number, payments: SalePaymentPayload[]): Promise<Sale> {
  const payload = await api<DataEnvelope<Sale>>(`/sales/${id}/payments`, {
    method: 'PUT',
    body: { payments },
  })
  return payload.data
}

export async function confirmSale(id: number, confirmBelowMinimum = false): Promise<Sale> {
  const payload = await api<DataEnvelope<Sale>>(`/sales/${id}/confirm`, {
    method: 'POST',
    body: { confirm_below_minimum: confirmBelowMinimum },
  })
  return payload.data
}

export async function cancelSale(id: number): Promise<Sale> {
  const payload = await api<DataEnvelope<Sale>>(`/sales/${id}/cancel`, { method: 'POST' })
  return payload.data
}
