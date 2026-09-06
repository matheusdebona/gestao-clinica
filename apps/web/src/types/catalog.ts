export interface Brand {
  id: number
  name: string
  is_active: boolean
  created_at?: string
  updated_at?: string
}

export interface ProductType {
  id: number
  brand_id: number
  name: string
  slug: string
  is_active: boolean
  brand?: Brand | null
  created_at?: string
  updated_at?: string
}

export interface UnitOfMeasure {
  id: number
  name: string
  symbol: string
  is_active: boolean
  created_at?: string
  updated_at?: string
}

export interface BrandPayload {
  name: string
  is_active?: boolean
}

export interface ProductTypePayload {
  name: string
  brand_id: number
  is_active?: boolean
}

export interface UnitPayload {
  name: string
  symbol: string
  is_active?: boolean
}
