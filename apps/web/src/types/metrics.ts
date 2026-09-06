export type MetricsGranularity = 'day' | 'week' | 'month'
export type AcquisitionGroupBy = 'origin' | 'campaign'
export type MarginMode = 'period' | 'cohort_sale'

export interface CommercialKpis {
  revenue: string
  sales_count: number
  ticket_avg: string
  avg_discount_percent: string | null
  list_total: string
  offered_total: string
}

export interface PaymentMixRow {
  payment_method_id: number
  name: string
  kind: string
  amount: string
  payments_count: number
}

export interface BudgetFunnel {
  by_status: Record<string, number>
  sent_in_period: number
  accepted_in_period: number
  rejected_in_period: number
  acceptance_rate: string | null
}

export interface RevenueSeriesPoint {
  period: string
  revenue: string
  sales_count: number
}

export interface CommercialMetrics {
  from: string
  to: string
  date_field: string
  granularity: MetricsGranularity
  kpis: CommercialKpis
  payment_mix: PaymentMixRow[]
  budget_funnel: BudgetFunnel
  series: RevenueSeriesPoint[]
}

export interface AcquisitionBreakdownRow {
  id: number | null
  key: string
  label: string
  new_clients: number
  consultation_revenue: string
  converted_clients: number
  conversion_rate: string | null
  sales_revenue: string
  sales_count: number
  avg_consultation_amount: string | null
  sales_to_consultation_ratio: string | null
  origin_label?: string | null
}

export interface AcquisitionKpis {
  new_clients: number
  consultation_revenue: string
  converted_clients: number
  conversion_rate: string | null
  sales_revenue: string
  sales_count: number
  avg_consultation_amount: string | null
  sales_to_consultation_ratio: string | null
}

export interface AcquisitionMetrics {
  from: string
  to: string
  group_by: AcquisitionGroupBy
  conversion: string
  conversion_definition: string
  kpis: AcquisitionKpis
  breakdown: AcquisitionBreakdownRow[]
}

export interface MarginKpis {
  sale_revenue: string
  extras_revenue: string
  revenue: string
  clinical_cost: string
  courtesy_cost: string
  gross_margin: string
  margin_percent: string | null
  pending_fulfillment_count: number | null
}

export interface MarginMetrics {
  from: string
  to: string
  mode: MarginMode
  kpis: MarginKpis
  notes: string
}

export interface LowStockProduct {
  id: number
  name: string
  sku: string | null
  stock_quantity: string
  min_stock: string
  lead_time_days: number
  coverage_days: string | null
}

export interface InventoryKpis {
  low_stock_count: number
  inventory_value: string
  negative_stock_count: number
  consumption_quantity: string
}

export interface InventoryMetrics {
  from: string
  to: string
  kpis: InventoryKpis
  low_stock_products: LowStockProduct[]
  notes: string[]
}

export interface PendingFulfillment {
  treatment_id: number
  sale_id: number
  client_id: number
  client_name: string | null
  remaining_units: string
  opened_at: string | null
}

export interface ProfessionalOpsRow {
  professional_user_id: number
  name: string | null
  sessions_count: number
  total_cost: string
}

export interface OperationsKpis {
  sessions_total: number
  cancellation_rate: string | null
  pending_fulfillment_units: string
  pending_fulfillment_treatments_count: number
}

export interface OperationsMetrics {
  from: string
  to: string
  date_field: string
  kpis: OperationsKpis
  sessions_by_status: Record<string, number>
  pending_fulfillments: PendingFulfillment[]
  by_professional: ProfessionalOpsRow[]
  notes: string[]
}
