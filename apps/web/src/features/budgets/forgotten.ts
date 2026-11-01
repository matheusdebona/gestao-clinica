import type { BudgetForgotten } from '@/types/budget'

export function forgottenLabel(info: BudgetForgotten): string {
  return info.days === 1 ? 'Sem resposta há 1 dia' : `Sem resposta há ${info.days} dias`
}

/** Last tier = danger; earlier tiers = warning. */
export function forgottenVariant(info: BudgetForgotten): 'warning' | 'danger' {
  return info.tier_index >= info.tiers - 1 ? 'danger' : 'warning'
}

export interface ForgottenBudgetRow {
  id: number
  sale_id: number
  client_id: number
  client?: { name: string; whatsapp: string } | null
  version: number
  effective_amount: string
  sent_at: string | null
  follow_up_sent_at?: string | null
  forgotten?: BudgetForgotten | null
}
