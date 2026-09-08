import { api } from '@/lib/api'
import type { DataEnvelope, Paginated } from '@/types/pagination'
import type {
  CardBrand,
  CardBrandPayload,
  CardOperator,
  CardOperatorPayload,
  PaymentMethod,
  PaymentMethodPayload,
} from '@/types/sale'

export interface PaymentCatalogListParams {
  page?: number
  active_only?: boolean
  kind?: string
}

function catalogQuery(params: PaymentCatalogListParams = {}): Record<string, string | number> {
  const query: Record<string, string | number> = {
    page: params.page ?? 1,
  }
  if (params.active_only) {
    query.is_active = 1
  }
  if (params.kind) {
    query.kind = params.kind
  }
  return query
}

async function listActive<T>(path: string): Promise<T[]> {
  const payload = await api<Paginated<T>>(path, {
    query: { page: 1, is_active: 1 },
  })
  return payload.data
}

export function listPaymentMethods(): Promise<PaymentMethod[]> {
  return listActive<PaymentMethod>('/payment-methods')
}

export function listCardOperators(): Promise<CardOperator[]> {
  return listActive<CardOperator>('/card-operators')
}

export function listCardBrands(): Promise<CardBrand[]> {
  return listActive<CardBrand>('/card-brands')
}

export async function listPaymentMethodsPage(
  params: PaymentCatalogListParams = {},
): Promise<Paginated<PaymentMethod>> {
  return api<Paginated<PaymentMethod>>('/payment-methods', { query: catalogQuery(params) })
}

export async function getPaymentMethod(id: number): Promise<PaymentMethod> {
  const payload = await api<DataEnvelope<PaymentMethod>>(`/payment-methods/${id}`)
  return payload.data
}

export async function createPaymentMethod(body: PaymentMethodPayload): Promise<PaymentMethod> {
  const payload = await api<DataEnvelope<PaymentMethod>>('/payment-methods', {
    method: 'POST',
    body,
  })
  return payload.data
}

export async function updatePaymentMethod(
  id: number,
  body: Partial<PaymentMethodPayload>,
): Promise<PaymentMethod> {
  const payload = await api<DataEnvelope<PaymentMethod>>(`/payment-methods/${id}`, {
    method: 'PUT',
    body,
  })
  return payload.data
}

export async function deactivatePaymentMethod(id: number): Promise<void> {
  await api(`/payment-methods/${id}`, { method: 'DELETE' })
}

export async function listCardBrandsPage(
  params: PaymentCatalogListParams = {},
): Promise<Paginated<CardBrand>> {
  return api<Paginated<CardBrand>>('/card-brands', { query: catalogQuery(params) })
}

export async function getCardBrand(id: number): Promise<CardBrand> {
  const payload = await api<DataEnvelope<CardBrand>>(`/card-brands/${id}`)
  return payload.data
}

export async function createCardBrand(body: CardBrandPayload): Promise<CardBrand> {
  const payload = await api<DataEnvelope<CardBrand>>('/card-brands', { method: 'POST', body })
  return payload.data
}

export async function updateCardBrand(
  id: number,
  body: Partial<CardBrandPayload>,
): Promise<CardBrand> {
  const payload = await api<DataEnvelope<CardBrand>>(`/card-brands/${id}`, {
    method: 'PUT',
    body,
  })
  return payload.data
}

export async function deactivateCardBrand(id: number): Promise<void> {
  await api(`/card-brands/${id}`, { method: 'DELETE' })
}

export async function listCardOperatorsPage(
  params: PaymentCatalogListParams = {},
): Promise<Paginated<CardOperator>> {
  return api<Paginated<CardOperator>>('/card-operators', { query: catalogQuery(params) })
}

export async function getCardOperator(id: number): Promise<CardOperator> {
  const payload = await api<DataEnvelope<CardOperator>>(`/card-operators/${id}`)
  return payload.data
}

export async function createCardOperator(body: CardOperatorPayload): Promise<CardOperator> {
  const payload = await api<DataEnvelope<CardOperator>>('/card-operators', { method: 'POST', body })
  return payload.data
}

export async function updateCardOperator(
  id: number,
  body: Partial<CardOperatorPayload>,
): Promise<CardOperator> {
  const payload = await api<DataEnvelope<CardOperator>>(`/card-operators/${id}`, {
    method: 'PUT',
    body,
  })
  return payload.data
}

export async function deactivateCardOperator(id: number): Promise<void> {
  await api(`/card-operators/${id}`, { method: 'DELETE' })
}
