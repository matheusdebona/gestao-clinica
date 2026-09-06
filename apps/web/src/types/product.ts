import type { Brand, ProductType, UnitOfMeasure } from './catalog'

export interface Product {
  id: number
  clinic_id: number
  name: string
  sku: string | null
  purpose: string | null
  product_type_id: number
  brand_id: number
  unit_of_measure_id: number
  product_type?: ProductType | null
  brand?: Brand | null
  unit_of_measure?: UnitOfMeasure | null
  cost: string
  sale_price: string
  min_sale_price: string | null
  stock_quantity: string
  min_stock: string
  lead_time_days: number
  unit_margin: string | null
  unit_margin_percent: string | null
  inventory_value: string
  is_low_stock: boolean
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface ProductPayload {
  name: string
  sku: string | null
  product_type_id: number
  brand_id: number
  unit_of_measure_id: number
  purpose: string | null
  cost?: string | null
  sale_price: string
  min_sale_price: string | null
  stock_quantity?: string | null
  min_stock: string | null
  lead_time_days: number
  is_active?: boolean
}

export interface StockAdjustPayload {
  type: 'in' | 'out'
  quantity: string
  unit_cost?: string | null
  reason?: string | null
  notes?: string | null
}

export interface StockAdjustResult {
  product: Product
  movement: {
    id: number
    type: string
    quantity: string
    unit_cost: string | null
  }
}
