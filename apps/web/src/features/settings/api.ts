import { api } from '@/lib/api'
import type { DataEnvelope } from '@/types/pagination'

export type WhatsAppTemplateKey = 'appointment_confirmation' | 'lead_follow_up' | 'patient_return'

export interface ClinicSettings {
  clinic_name: string
  whatsapp_templates: Record<WhatsAppTemplateKey, string>
  whatsapp_template_defaults: Record<WhatsAppTemplateKey, string>
  whatsapp_placeholders: Record<WhatsAppTemplateKey, string[]>
}

export interface ClinicSettingsPayload {
  whatsapp_templates?: Partial<Record<WhatsAppTemplateKey, string | null>>
}

export async function getClinicSettings(): Promise<ClinicSettings> {
  const payload = await api<DataEnvelope<ClinicSettings>>('/clinic/settings')
  return payload.data
}

export async function updateClinicSettings(body: ClinicSettingsPayload): Promise<ClinicSettings> {
  const payload = await api<DataEnvelope<ClinicSettings>>('/clinic/settings', { method: 'PUT', body })
  return payload.data
}
