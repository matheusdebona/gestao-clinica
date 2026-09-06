import { api } from '@/lib/api'
import type { DataEnvelope, Paginated } from '@/types/pagination'
import type { Product, ProductPayload, StockAdjustPayload, StockAdjustResult } from '@/types/product'

export interface ProductListParams {
  q?: string
  page?: number
  is_active?: boolean
  low_stock?: boolean
  product_type_id?: number
}

export async function listProducts(params: ProductListParams = {}): Promise<Paginated<Product>> {
  const query: Record<string, string | number> = {
    page: params.page ?? 1,
  }
  if (params.q) {
    query.q = params.q
  }
  if (params.is_active !== undefined) {
    query.is_active = params.is_active ? 1 : 0
  }
  if (params.low_stock) {
    query.low_stock = 1
  }
  if (params.product_type_id) {
    query.product_type_id = params.product_type_id
  }

  return api<Paginated<Product>>('/products', { query })
}

export async function getProduct(id: number): Promise<Product> {
  const payload = await api<DataEnvelope<Product>>(`/products/${id}`)
  return payload.data
}

export async function createProduct(body: ProductPayload): Promise<Product> {
  const payload = await api<DataEnvelope<Product>>('/products', { method: 'POST', body })
  return payload.data
}

export async function updateProduct(id: number, body: Partial<ProductPayload>): Promise<Product> {
  const payload = await api<DataEnvelope<Product>>(`/products/${id}`, { method: 'PUT', body })
  return payload.data
}

export async function deactivateProduct(id: number): Promise<void> {
  await api(`/products/${id}`, { method: 'DELETE' })
}

export async function adjustStock(id: number, body: StockAdjustPayload): Promise<StockAdjustResult> {
  const payload = await api<DataEnvelope<StockAdjustResult>>(`/products/${id}/stock-movements`, {
    method: 'POST',
    body,
  })
  return payload.data
}
