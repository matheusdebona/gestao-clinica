import { api } from '@/lib/api'
import type { DataEnvelope, Paginated } from '@/types/pagination'
import type { ClinicNotification, NotificationCategory } from '@/types/notification'

export interface NotificationListParams {
  page?: number
  unread?: boolean
  category?: NotificationCategory
}

export async function listNotifications(
  params: NotificationListParams = {},
): Promise<Paginated<ClinicNotification>> {
  const query: Record<string, string | number> = {
    page: params.page ?? 1,
  }
  if (params.unread) {
    query.unread = 1
  }
  if (params.category) {
    query.category = params.category
  }

  return api<Paginated<ClinicNotification>>('/notifications', { query })
}

export async function getUnreadNotificationCount(): Promise<number> {
  const payload = await api<DataEnvelope<{ unread_count: number }>>('/notifications/unread-count')
  return payload.data.unread_count
}

export async function markNotificationRead(id: string): Promise<ClinicNotification> {
  const payload = await api<DataEnvelope<ClinicNotification>>(`/notifications/${id}/read`, {
    method: 'POST',
  })
  return payload.data
}

export async function markAllNotificationsRead(): Promise<void> {
  await api('/notifications/read-all', { method: 'POST' })
}
