import { api } from '@/lib/api'
import type { Appointment, AppointmentPayload, ConsumptionPayload } from '@/types/appointment'
import type { DataEnvelope, Paginated } from '@/types/pagination'
import type { ClinicUser } from '@/types/team-user'

export interface AppointmentListParams {
  from?: string
  to?: string
  status?: string
  professional_user_id?: number
  client_id?: number
  q?: string
  page?: number
  per_page?: number
}

export async function listAppointments(
  params: AppointmentListParams = {},
): Promise<Paginated<Appointment>> {
  const query: Record<string, string | number> = {}
  if (params.from) {
    query.from = params.from
  }
  if (params.to) {
    query.to = params.to
  }
  if (params.status) {
    query.status = params.status
  }
  if (params.professional_user_id) {
    query.professional_user_id = params.professional_user_id
  }
  if (params.client_id) {
    query.client_id = params.client_id
  }
  if (params.q) {
    query.q = params.q
  }
  if (params.page) {
    query.page = params.page
  }
  if (params.per_page) {
    query.per_page = params.per_page
  }

  return api<Paginated<Appointment>>('/appointments', { query })
}

export async function getAppointment(id: number): Promise<Appointment> {
  const payload = await api<DataEnvelope<Appointment>>(`/appointments/${id}`)
  return payload.data
}

export async function createAppointment(
  treatmentId: number,
  body: AppointmentPayload,
): Promise<Appointment> {
  const payload = await api<DataEnvelope<Appointment>>(`/treatments/${treatmentId}/appointments`, {
    method: 'POST',
    body,
  })
  return payload.data
}

export async function updateAppointment(
  id: number,
  body: Partial<AppointmentPayload>,
): Promise<Appointment> {
  const payload = await api<DataEnvelope<Appointment>>(`/appointments/${id}`, {
    method: 'PATCH',
    body,
  })
  return payload.data
}

export async function startAppointment(id: number): Promise<Appointment> {
  const payload = await api<DataEnvelope<Appointment>>(`/appointments/${id}/start`, {
    method: 'POST',
    body: {},
  })
  return payload.data
}

export async function cancelAppointment(id: number): Promise<Appointment> {
  const payload = await api<DataEnvelope<Appointment>>(`/appointments/${id}/cancel`, {
    method: 'POST',
    body: {},
  })
  return payload.data
}

export async function syncAppointmentConsumptions(
  id: number,
  consumptions: ConsumptionPayload[],
): Promise<Appointment> {
  const payload = await api<DataEnvelope<Appointment>>(`/appointments/${id}/consumptions`, {
    method: 'PUT',
    body: { consumptions },
  })
  return payload.data
}

export async function completeAppointment(id: number): Promise<Appointment> {
  const payload = await api<DataEnvelope<Appointment>>(`/appointments/${id}/complete`, {
    method: 'POST',
    body: {},
  })
  return payload.data
}

export async function listProfessionals(): Promise<ClinicUser[]> {
  const payload = await api<{ data: ClinicUser[] }>('/professionals')
  return payload.data
}
