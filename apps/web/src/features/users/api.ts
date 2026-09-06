import { api } from '@/lib/api'
import type { DataEnvelope, Paginated } from '@/types/pagination'
import type { AssignableRole, ClinicUser, UserPayload } from '@/types/team-user'

export interface UserListParams {
  page?: number
}

export async function listUsers(params: UserListParams = {}): Promise<Paginated<ClinicUser>> {
  return api<Paginated<ClinicUser>>('/users', {
    query: { page: params.page ?? 1 },
  })
}

export async function getUser(id: number): Promise<ClinicUser> {
  const payload = await api<DataEnvelope<ClinicUser>>(`/users/${id}`)
  return payload.data
}

export async function createUser(body: UserPayload): Promise<ClinicUser> {
  const payload = await api<DataEnvelope<ClinicUser>>('/users', {
    method: 'POST',
    body,
  })
  return payload.data
}

export async function updateUser(id: number, body: Partial<UserPayload>): Promise<ClinicUser> {
  const payload = await api<DataEnvelope<ClinicUser>>(`/users/${id}`, {
    method: 'PUT',
    body,
  })
  return payload.data
}

export async function deactivateUser(id: number): Promise<void> {
  await api(`/users/${id}`, { method: 'DELETE' })
}

export async function listAssignableRoles(): Promise<AssignableRole[]> {
  const payload = await api<{ data: AssignableRole[] }>('/roles', {
    query: { assignable: 1 },
  })
  return payload.data
}
