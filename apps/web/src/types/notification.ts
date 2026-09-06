export interface NotificationData {
  type?: string
  title?: string
  message?: string
  product_id?: number | string
  appointment_id?: number | string
  treatment_id?: number | string
  product_name?: string
  warnings?: string[]
  [key: string]: unknown
}

export interface ClinicNotification {
  id: string
  type: string
  type_key: string | null
  data: NotificationData
  read_at: string | null
  created_at: string
  updated_at: string
}

export type NotificationCategory = 'stock' | 'agenda'

export type NotificationInboxFilter = 'all' | 'unread' | 'stock' | 'agenda'
