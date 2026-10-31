import { computed } from 'vue'
import { useClinicSettings } from '@/features/settings/useClinicSettings'
import {
  firstName,
  renderWhatsAppTemplate,
  whatsappDateParts,
  whatsappUrl,
} from '@/lib/whatsapp'
import { useAuthStore } from '@/stores/auth'

/** Builds wa.me links from the clinic templates (falls back to the defaults while loading). */
export function useWhatsAppLinks() {
  const auth = useAuthStore()
  const { data: settings } = useClinicSettings()

  const base = computed(() => ({
    clinica: settings.value?.clinic_name || auth.clinicName,
    usuario: firstName(auth.user?.name),
  }))

  function template(key: 'appointment_confirmation' | 'lead_follow_up'): string {
    return settings.value?.whatsapp_templates[key] ?? FALLBACKS[key]
  }

  function leadUrl(lead: { name: string; phone: string }): string | null {
    const text = renderWhatsAppTemplate(template('lead_follow_up'), {
      ...base.value,
      paciente: firstName(lead.name),
    })
    return whatsappUrl(lead.phone, text)
  }

  function appointmentUrl(appointment: {
    scheduled_at: string | null
    client?: { name: string; whatsapp: string } | null
    professional?: { name: string } | null
  }): string | null {
    if (!appointment.client) {
      return null
    }
    const text = renderWhatsAppTemplate(template('appointment_confirmation'), {
      ...base.value,
      ...whatsappDateParts(appointment.scheduled_at),
      paciente: firstName(appointment.client.name),
      profissional: appointment.professional?.name ?? '',
    })
    return whatsappUrl(appointment.client.whatsapp, text)
  }

  return { leadUrl, appointmentUrl }
}

const FALLBACKS = {
  appointment_confirmation:
    'Olá, {paciente}! Aqui é da {clinica}. Passando para confirmar seu horário no dia {data} às {hora} com {profissional}. Podemos confirmar?',
  lead_follow_up:
    'Olá, {paciente}! Aqui é {usuario}, da {clinica}. Vi seu interesse e queria saber se posso te ajudar a agendar uma avaliação.',
} as const
