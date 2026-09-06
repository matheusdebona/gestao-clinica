import type { ClinicNotification } from '@/types/notification'

export function notificationTypeKey(
  item: Pick<ClinicNotification, 'type_key' | 'data'>,
): string | null {
  if (item.type_key) {
    return item.type_key
  }
  const type = item.data?.type
  return typeof type === 'string' && type.length > 0 ? type : null
}

export function notificationTitle(item: Pick<ClinicNotification, 'data'>): string {
  const title = item.data?.title
  return typeof title === 'string' && title.trim() ? title.trim() : 'Alerta'
}

export function notificationMessage(item: Pick<ClinicNotification, 'data'>): string {
  const message = item.data?.message
  return typeof message === 'string' ? message.trim() : ''
}

export function notificationHref(item: ClinicNotification): string | null {
  const type = notificationTypeKey(item)
  const productId = numericId(item.data?.product_id)
  const appointmentId = numericId(item.data?.appointment_id)

  if (type === 'low_stock' || type === 'projected_low_stock') {
    return productId !== null ? `/products/${productId}` : null
  }

  if (type === 'appointment_stock_warning') {
    return appointmentId !== null ? `/appointments/${appointmentId}` : null
  }

  return null
}

export function isNotificationUnread(item: Pick<ClinicNotification, 'read_at'>): boolean {
  return !item.read_at
}

function numericId(value: unknown): number | null {
  if (typeof value === 'number' && Number.isInteger(value) && value > 0) {
    return value
  }
  if (typeof value === 'string' && /^\d+$/.test(value)) {
    const parsed = Number(value)
    return parsed > 0 ? parsed : null
  }
  return null
}
