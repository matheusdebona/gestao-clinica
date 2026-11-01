import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { emptyToNull } from '@/lib/formatters'
import { phoneDigits } from '@/lib/masks'
import type { Lead, LeadPayload } from '@/types/lead'

export const leadFormZod = z.object({
    name: z.string().trim().min(1, 'Informe o nome.').max(255, 'Nome muito longo.'),
    // Opcional: o lead pode ainda não ter passado o número (obrigatório só para agendar).
    phone: z
      .string()
      .trim()
      .refine((value) => {
        const digits = phoneDigits(value)
        return digits.length === 0 || digits.length === 10 || digits.length === 11
      }, 'Informe DDD e número.'),
    client_origin_id: z.string().min(1, 'Selecione a origem.'),
    lead_status_id: z.string(),
    notes: z.string().max(5000, 'Observação muito longa.'),
})

export const leadFormSchema = toTypedSchema(leadFormZod)

export type LeadFormValues = {
  name: string
  phone: string
  client_origin_id: string
  lead_status_id: string
  notes: string
}

export const emptyLeadForm = (): LeadFormValues => ({
  name: '',
  phone: '',
  client_origin_id: '',
  lead_status_id: '',
  notes: '',
})

export function leadToForm(lead: Lead): LeadFormValues {
  return {
    name: lead.name,
    phone: lead.phone ?? '',
    client_origin_id: String(lead.client_origin_id),
    lead_status_id: String(lead.lead_status_id),
    notes: lead.notes ?? '',
  }
}

export function toLeadPayload(values: LeadFormValues): LeadPayload {
  return {
    name: values.name.trim(),
    phone: phoneDigits(values.phone) || null,
    client_origin_id: Number(values.client_origin_id),
    lead_status_id: values.lead_status_id ? Number(values.lead_status_id) : null,
    notes: emptyToNull(values.notes),
  }
}
