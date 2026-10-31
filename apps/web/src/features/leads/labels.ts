import type { LeadActivity, LeadStatusColor } from '@/types/lead'

export const LEAD_CONVERTED_KEY = 'converted'
export const LEAD_CANCELLED_KEY = 'cancelled'

/** System statuses are set only by their flows (Agendar / Cancelar lead). */
export const LEAD_SYSTEM_KEYS = [LEAD_CONVERTED_KEY, LEAD_CANCELLED_KEY]

export const LEAD_STATUS_COLOR_OPTIONS: { value: LeadStatusColor; label: string }[] = [
  { value: 'muted', label: 'Cinza' },
  { value: 'purple', label: 'Violeta' },
  { value: 'warning', label: 'Laranja' },
  { value: 'success', label: 'Verde' },
  { value: 'danger', label: 'Vermelho' },
]

export function activityLabel(activity: LeadActivity): string {
  switch (activity.type) {
    case 'created':
      return activity.to_status ? `Cadastrado como ${activity.to_status.name}` : 'Cadastrado'
    case 'converted':
      return 'Virou paciente (avaliação agendada)'
    case 'cancelled':
      return 'Lead cancelado'
    case 'comment':
      return 'Comentário'
    case 'whatsapp':
      return activity.user
        ? `Mensagem de WhatsApp aberta por ${activity.user.name}`
        : 'Mensagem de WhatsApp aberta'
    case 'approached':
      return activity.user ? `Abordado por ${activity.user.name}` : 'Abordado'
    case 'replied':
      return activity.user ? `Respondeu (marcado por ${activity.user.name})` : 'Respondeu'
    case 'booked':
      return activity.user ? `Agendou (com ${activity.user.name})` : 'Agendou'
    case 'status_changed':
      if (activity.from_status && activity.to_status) {
        return `${activity.from_status.name} → ${activity.to_status.name}`
      }
      return activity.to_status ? `Status: ${activity.to_status.name}` : 'Status alterado'
    default:
      return 'Observação'
  }
}
