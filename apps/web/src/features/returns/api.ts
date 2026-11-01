import { api } from '@/lib/api'
import type { DataEnvelope } from '@/types/pagination'

export type PatientReturnFilter = 'due' | 'overdue' | 'upcoming' | 'done'

export interface PatientReturn {
  id: number
  client_id: number
  client: { id: number; name: string; whatsapp: string } | null
  protocol_id: number | null
  sale_id: number | null
  treatment_id: number | null
  label: string
  due_on: string
  /** Negative = overdue. */
  days_until_due: number
  status: 'pending' | 'done' | 'cancelled'
  done_at: string | null
  done_reason: 'appointment' | 'sale' | 'manual' | null
  whatsapp_sent_at: string | null
}

export interface PatientReturnsResponse {
  data: PatientReturn[]
  meta: { filter: PatientReturnFilter; today: string; counts: { due: number; overdue: number } }
}

export async function listPatientReturns(
  filter: PatientReturnFilter = 'due',
): Promise<PatientReturnsResponse> {
  return api<PatientReturnsResponse>('/patient-returns', { query: { filter } })
}

export async function markPatientReturnDone(id: number): Promise<PatientReturn> {
  const payload = await api<DataEnvelope<PatientReturn>>(`/patient-returns/${id}/done`, {
    method: 'POST',
    body: {},
  })
  return payload.data
}

export async function logPatientReturnWhatsApp(id: number): Promise<PatientReturn> {
  const payload = await api<DataEnvelope<PatientReturn>>(`/patient-returns/${id}/whatsapp`, {
    method: 'POST',
    body: {},
  })
  return payload.data
}

export function returnDueLabel(days: number): string {
  if (days < 0) {
    return days === -1 ? 'Atrasado há 1 dia' : `Atrasado há ${-days} dias`
  }
  if (days === 0) {
    return 'Vence hoje'
  }
  return days === 1 ? 'Vence amanhã' : `Vence em ${days} dias`
}

export function returnDueVariant(days: number): 'danger' | 'warning' | 'purple' {
  if (days < 0) {
    return 'danger'
  }
  return days <= 2 ? 'warning' : 'purple'
}
