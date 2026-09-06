import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { emptyToInt, emptyToMoney, emptyToNull } from '@/lib/formatters'
import type { ClientPayload } from '@/types/client'

export const NONE_VALUE = '__none__'

export const clientFormSchema = toTypedSchema(
  z.object({
    name: z.string().trim().min(1, 'Informe o nome.').max(255, 'Nome muito longo.'),
    whatsapp: z.string().trim().min(1, 'Informe o WhatsApp.').max(30, 'WhatsApp muito longo.'),
    notes: z.string(),
    main_pains: z.string(),
    service_duration_minutes: z
      .string()
      .refine((value) => {
        if (!value.trim()) {
          return true
        }
        const parsed = Number(value)
        return Number.isInteger(parsed) && parsed >= 1 && parsed <= 1440
      }, 'Informe minutos entre 1 e 1440.'),
    client_origin_id: z.string(),
    campaign_id: z.string(),
    initial_consultation_amount: z.string(),
  }),
)

export type ClientFormValues = {
  name: string
  whatsapp: string
  notes: string
  main_pains: string
  service_duration_minutes: string
  client_origin_id: string
  campaign_id: string
  initial_consultation_amount: string
}

export const emptyClientForm = (): ClientFormValues => ({
  name: '',
  whatsapp: '',
  notes: '',
  main_pains: '',
  service_duration_minutes: '',
  client_origin_id: NONE_VALUE,
  campaign_id: NONE_VALUE,
  initial_consultation_amount: '',
})

export function toClientPayload(values: ClientFormValues): ClientPayload {
  const originId =
    values.client_origin_id === NONE_VALUE ? null : emptyToInt(values.client_origin_id)
  const campaignId = values.campaign_id === NONE_VALUE ? null : emptyToInt(values.campaign_id)

  return {
    name: values.name.trim(),
    whatsapp: values.whatsapp.trim(),
    notes: emptyToNull(values.notes),
    main_pains: emptyToNull(values.main_pains),
    service_duration_minutes: emptyToInt(values.service_duration_minutes),
    client_origin_id: originId,
    campaign_id: originId ? campaignId : null,
    initial_consultation_amount: emptyToMoney(values.initial_consultation_amount),
  }
}
