import { api } from '@/lib/api'
import type {
  Brand,
  BrandPayload,
  ProductType,
  ProductTypePayload,
  UnitOfMeasure,
  UnitPayload,
} from '@/types/catalog'
import type { DataEnvelope, Paginated } from '@/types/pagination'

export interface CatalogListParams {
  page?: number
  active_only?: boolean
  brand_id?: number
}

function catalogQuery(params: CatalogListParams = {}): Record<string, string | number> {
  const query: Record<string, string | number> = {
    page: params.page ?? 1,
  }
  if (params.active_only) {
    query.active_only = 1
  }
  if (params.brand_id) {
    query.brand_id = params.brand_id
  }
  return query
}

export async function listBrands(params: CatalogListParams = {}): Promise<Paginated<Brand>> {
  return api<Paginated<Brand>>('/brands', { query: catalogQuery(params) })
}

export async function getBrand(id: number): Promise<Brand> {
  const payload = await api<DataEnvelope<Brand>>(`/brands/${id}`)
  return payload.data
}

export async function createBrand(body: BrandPayload): Promise<Brand> {
  const payload = await api<DataEnvelope<Brand>>('/brands', { method: 'POST', body })
  return payload.data
}

export async function updateBrand(id: number, body: Partial<BrandPayload>): Promise<Brand> {
  const payload = await api<DataEnvelope<Brand>>(`/brands/${id}`, { method: 'PUT', body })
  return payload.data
}

export async function deactivateBrand(id: number): Promise<void> {
  await api(`/brands/${id}`, { method: 'DELETE' })
}

export async function listProductTypes(params: CatalogListParams = {}): Promise<Paginated<ProductType>> {
  return api<Paginated<ProductType>>('/product-types', { query: catalogQuery(params) })
}

export async function getProductType(id: number): Promise<ProductType> {
  const payload = await api<DataEnvelope<ProductType>>(`/product-types/${id}`)
  return payload.data
}

export async function createProductType(body: ProductTypePayload): Promise<ProductType> {
  const payload = await api<DataEnvelope<ProductType>>('/product-types', { method: 'POST', body })
  return payload.data
}

export async function updateProductType(
  id: number,
  body: Partial<ProductTypePayload>,
): Promise<ProductType> {
  const payload = await api<DataEnvelope<ProductType>>(`/product-types/${id}`, { method: 'PUT', body })
  return payload.data
}

export async function deactivateProductType(id: number): Promise<void> {
  await api(`/product-types/${id}`, { method: 'DELETE' })
}

export async function listUnits(params: CatalogListParams = {}): Promise<Paginated<UnitOfMeasure>> {
  return api<Paginated<UnitOfMeasure>>('/units-of-measure', { query: catalogQuery(params) })
}

export async function getUnit(id: number): Promise<UnitOfMeasure> {
  const payload = await api<DataEnvelope<UnitOfMeasure>>(`/units-of-measure/${id}`)
  return payload.data
}

export async function createUnit(body: UnitPayload): Promise<UnitOfMeasure> {
  const payload = await api<DataEnvelope<UnitOfMeasure>>('/units-of-measure', { method: 'POST', body })
  return payload.data
}

export async function updateUnit(id: number, body: Partial<UnitPayload>): Promise<UnitOfMeasure> {
  const payload = await api<DataEnvelope<UnitOfMeasure>>(`/units-of-measure/${id}`, {
    method: 'PUT',
    body,
  })
  return payload.data
}

export async function deactivateUnit(id: number): Promise<void> {
  await api(`/units-of-measure/${id}`, { method: 'DELETE' })
}
