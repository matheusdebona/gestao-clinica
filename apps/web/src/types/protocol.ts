import type { Product } from './product'

export interface ProtocolItem {
  id: number
  product_id: number
  quantity: string
  line_cost?: string | null
  line_sale?: string | null
  product?: Product | null
}

export interface Protocol {
  id: number
  clinic_id: number
  name: string
  description: string | null
  total_cost: string
  products_sale_total: string
  suggested_price: string
  suggested_price_is_manual: boolean
  min_price: string
  min_price_is_manual: boolean
  special_price: string | null
  margin_at_suggested: string
  margin_at_min: string
  margin_at_special: string | null
  is_active: boolean
  items?: ProtocolItem[]
  created_at: string
  updated_at: string
}

export interface ProtocolItemPayload {
  product_id: number
  quantity: string
}

export interface ProtocolHeaderPayload {
  name: string
  description: string | null
  is_active: boolean
  suggested_price?: string
  min_price?: string
  special_price?: string | null
}

export interface ProtocolCreatePayload extends ProtocolHeaderPayload {
  items: ProtocolItemPayload[]
}

export interface ProtocolSavePayload {
  header: ProtocolHeaderPayload
  items: ProtocolItemPayload[]
}
