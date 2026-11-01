import { api } from '@/lib/api'
import type { DataEnvelope } from '@/types/pagination'
import type {
  AcquisitionGroupBy,
  AcquisitionMetrics,
  CommercialMetrics,
  InventoryMetrics,
  LeadMetrics,
  MarginMetrics,
  MarginMode,
  MetricsGranularity,
  OperationsMetrics,
  SalesFunnelMetrics,
} from '@/types/metrics'

export interface MetricsPeriodParams {
  from: string
  to: string
}

export async function getCommercialMetrics(
  params: MetricsPeriodParams & { granularity?: MetricsGranularity },
): Promise<CommercialMetrics> {
  const payload = await api<DataEnvelope<CommercialMetrics>>('/metrics/commercial', {
    query: params,
  })
  return payload.data
}

export async function getAcquisitionMetrics(
  params: MetricsPeriodParams & { group_by?: AcquisitionGroupBy },
): Promise<AcquisitionMetrics> {
  const payload = await api<DataEnvelope<AcquisitionMetrics>>('/metrics/acquisition', {
    query: params,
  })
  return payload.data
}

export async function getMarginMetrics(
  params: MetricsPeriodParams & { mode?: MarginMode },
): Promise<MarginMetrics> {
  const payload = await api<DataEnvelope<MarginMetrics>>('/metrics/margin', {
    query: params,
  })
  return payload.data
}

export async function getInventoryMetrics(params: MetricsPeriodParams): Promise<InventoryMetrics> {
  const payload = await api<DataEnvelope<InventoryMetrics>>('/metrics/inventory', {
    query: params,
  })
  return payload.data
}

export async function getOperationsMetrics(params: MetricsPeriodParams): Promise<OperationsMetrics> {
  const payload = await api<DataEnvelope<OperationsMetrics>>('/metrics/operations', {
    query: params,
  })
  return payload.data
}

export async function getLeadMetrics(params: MetricsPeriodParams): Promise<LeadMetrics> {
  const payload = await api<DataEnvelope<LeadMetrics>>('/metrics/leads', {
    query: params,
  })
  return payload.data
}

/** End-to-end funnel (lead → venda). Filters: origem and who registered the lead. */
export async function getSalesFunnelMetrics(
  params: MetricsPeriodParams & { client_origin_id?: number; user_id?: number },
): Promise<SalesFunnelMetrics> {
  const payload = await api<DataEnvelope<SalesFunnelMetrics>>('/metrics/sales-funnel', {
    query: params,
  })
  return payload.data
}
