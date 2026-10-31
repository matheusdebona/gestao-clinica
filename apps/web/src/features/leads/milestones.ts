import type { Lead } from '@/types/lead'
import type { LeadQuickMilestone } from '@/features/leads/api'

export const MILESTONE_LABELS = {
  approached: 'Abordado',
  replied: 'Respondeu',
  booked: 'Agendou',
} as const

/** Next quick action for an open lead: Abordado → Respondeu (Agendou comes from "Agendar"). */
export function nextQuickMilestone(
  lead: Pick<Lead, 'client_id' | 'cancelled_at' | 'approached_at' | 'replied_at'>,
): LeadQuickMilestone | null {
  if (lead.client_id || lead.cancelled_at) {
    return null
  }
  if (!lead.approached_at) {
    return 'approached'
  }
  if (!lead.replied_at) {
    return 'replied'
  }
  return null
}

export function quickMilestoneLabel(milestone: LeadQuickMilestone): string {
  return milestone === 'approached' ? 'Marcar abordado' : 'Respondeu'
}
