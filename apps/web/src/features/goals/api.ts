import { api } from '@/lib/api'
import type { DataEnvelope } from '@/types/pagination'

export type GoalMetricKey = 'appointments' | 'sales' | 'approached'

export interface GoalMetric {
  key: GoalMetricKey
  label: string
  money: boolean
  target: number | string | null
  actual: number | string
  remaining: number | string | null
  percent: number | null
}

export interface GoalProgress {
  user: { id: number; name: string }
  month: string
  has_goal: boolean
  /** YYYY-MM of the goal being reused when this month has none of its own. */
  inherited_from: string | null
  targets: {
    appointments_target: number | null
    sales_target: string | null
    approached_target: number | null
  }
  /** Share of the month already elapsed (pace reference). */
  expected_percent: number
  metrics: GoalMetric[]
}

export interface GoalPayload {
  month: string
  appointments_target: number | null
  sales_target: string | null
  approached_target: number | null
}

export async function getMyGoal(month?: string): Promise<GoalProgress> {
  const payload = await api<DataEnvelope<GoalProgress>>('/goals/me', {
    query: month ? { month } : {},
  })
  return payload.data
}

export async function listTeamGoals(
  month?: string,
): Promise<{ data: GoalProgress[]; meta: { month: string; can_edit: boolean } }> {
  return api('/goals', { query: month ? { month } : {} })
}

export async function saveGoal(userId: number, body: GoalPayload): Promise<GoalProgress> {
  const payload = await api<DataEnvelope<GoalProgress>>(`/goals/${userId}`, {
    method: 'PUT',
    body,
  })
  return payload.data
}

export function monthLabel(month: string): string {
  const [y, m] = month.split('-').map(Number)
  if (!y || !m) {
    return month
  }
  const label = new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' }).format(
    new Date(y, m - 1, 1),
  )
  return label.charAt(0).toUpperCase() + label.slice(1)
}

export function currentMonth(): string {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'America/Sao_Paulo',
    year: 'numeric',
    month: '2-digit',
  }).format(new Date())
  return parts.slice(0, 7)
}

export function shiftMonth(month: string, delta: number): string {
  const [y, m] = month.split('-').map(Number)
  const date = new Date(y!, m! - 1 + delta, 1)
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
}
