import { api } from '@/lib/api'
import type { Budget, BudgetCreatePayload, BudgetStatus, DocumentRecord } from '@/types/budget'
import type { DataEnvelope, Paginated } from '@/types/pagination'

export interface BudgetListParams {
  page?: number
  status?: BudgetStatus | ''
  client_id?: number
  sale_id?: number
  include_superseded?: boolean
}

export async function listBudgets(params: BudgetListParams = {}): Promise<Paginated<Budget>> {
  const query: Record<string, string | number> = {
    page: params.page ?? 1,
  }
  if (params.status) {
    query.status = params.status
  }
  if (params.client_id) {
    query.client_id = params.client_id
  }
  if (params.sale_id) {
    query.sale_id = params.sale_id
  }
  if (params.include_superseded) {
    query.include_superseded = 1
  }

  return api<Paginated<Budget>>('/budgets', { query })
}

export async function listSaleBudgets(saleId: number): Promise<Paginated<Budget>> {
  return api<Paginated<Budget>>(`/sales/${saleId}/budgets`, { query: { page: 1 } })
}

export async function createBudget(saleId: number, body: BudgetCreatePayload = {}): Promise<Budget> {
  const payload = await api<DataEnvelope<Budget>>(`/sales/${saleId}/budgets`, {
    method: 'POST',
    body,
  })
  return payload.data
}

export async function sendBudget(id: number): Promise<Budget> {
  const payload = await api<DataEnvelope<Budget>>(`/budgets/${id}/send`, { method: 'POST' })
  return payload.data
}

export async function acceptBudget(id: number): Promise<Budget> {
  const payload = await api<DataEnvelope<Budget>>(`/budgets/${id}/accept`, { method: 'POST' })
  return payload.data
}

export async function rejectBudget(id: number): Promise<Budget> {
  const payload = await api<DataEnvelope<Budget>>(`/budgets/${id}/reject`, { method: 'POST' })
  return payload.data
}

export async function expireBudget(id: number): Promise<Budget> {
  const payload = await api<DataEnvelope<Budget>>(`/budgets/${id}/expire`, { method: 'POST' })
  return payload.data
}

export async function generateBudgetPdf(id: number): Promise<DocumentRecord> {
  const payload = await api<DataEnvelope<DocumentRecord>>(`/budgets/${id}/pdf`, { method: 'POST' })
  return payload.data
}

export async function downloadDocument(id: number, filename: string): Promise<void> {
  const response = await api.raw(`/documents/${id}/download`)
  const blob = await response.blob()
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}
