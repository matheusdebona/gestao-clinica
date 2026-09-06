import { api } from '@/lib/api'
import type { Paginated } from '@/types/pagination'
import type { CardBrand, CardOperator, PaymentMethod } from '@/types/sale'

async function listAll<T>(path: string): Promise<T[]> {
  const payload = await api<Paginated<T>>(path, {
    query: { page: 1, is_active: 1 },
  })
  return payload.data
}

export function listPaymentMethods(): Promise<PaymentMethod[]> {
  return listAll<PaymentMethod>('/payment-methods')
}

export function listCardOperators(): Promise<CardOperator[]> {
  return listAll<CardOperator>('/card-operators')
}

export function listCardBrands(): Promise<CardBrand[]> {
  return listAll<CardBrand>('/card-brands')
}
