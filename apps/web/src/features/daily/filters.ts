import type { LocationQuery, LocationQueryRaw } from 'vue-router'
import type { DailyParams, DailyTeamRow } from '@/features/daily/api'

export type DailyPeriod = 'dia' | '7d' | '30d' | 'custom'
export type DailyTab = 'resumo' | 'equipe' | 'checkins'

export interface DailyFilters {
  tab: DailyTab
  period: DailyPeriod
  day: string
  from: string
  to: string
  userIds: number[]
  originId: string
  statusId: string
  staleDays: string
}

const ISO = /^\d{4}-\d{2}-\d{2}$/
const TZ = 'America/Sao_Paulo'

/** Today (or `now`) as a calendar day in São Paulo. */
export function clinicToday(now = new Date()): string {
  return new Intl.DateTimeFormat('en-CA', { timeZone: TZ, year: 'numeric', month: '2-digit', day: '2-digit' }).format(now)
}

export function shiftDay(day: string, delta: number): string {
  const [y, m, d] = day.split('-').map(Number)
  const date = new Date(Date.UTC(y!, m! - 1, d! + delta))
  return date.toISOString().slice(0, 10)
}

export const DAILY_PERIOD_ITEMS: { value: DailyPeriod; label: string }[] = [
  { value: 'dia', label: 'Dia' },
  { value: '7d', label: '7 dias' },
  { value: '30d', label: '30 dias' },
  { value: 'custom', label: 'Período' },
]

function str(value: LocationQuery[string]): string {
  const raw = Array.isArray(value) ? value[0] : value
  return typeof raw === 'string' ? raw : ''
}

function ids(value: string): number[] {
  return value
    .split(',')
    .map(Number)
    .filter((id) => Number.isInteger(id) && id > 0)
}

export function dailyFiltersFromQuery(query: LocationQuery, now = new Date()): DailyFilters {
  const today = clinicToday(now)
  const tab = str(query.aba)
  const period = str(query.periodo) as DailyPeriod
  return {
    tab: tab === 'equipe' || tab === 'checkins' ? tab : 'resumo',
    period: ['dia', '7d', '30d', 'custom'].includes(period) ? period : 'dia',
    day: ISO.test(str(query.data)) ? str(query.data) : today,
    from: ISO.test(str(query.de)) ? str(query.de) : '',
    to: ISO.test(str(query.ate)) ? str(query.ate) : '',
    userIds: ids(str(query.usuarios)),
    originId: /^\d+$/.test(str(query.origem)) ? str(query.origem) : '',
    statusId: /^\d+$/.test(str(query.status)) ? str(query.status) : '',
    staleDays: /^\d+$/.test(str(query.parados)) ? str(query.parados) : '',
  }
}

export function dailyFiltersToQuery(filters: DailyFilters, now = new Date()): LocationQueryRaw {
  const query: LocationQueryRaw = {}
  if (filters.tab !== 'resumo') query.aba = filters.tab
  if (filters.period !== 'dia') query.periodo = filters.period
  if (filters.period === 'dia' && filters.day !== clinicToday(now)) query.data = filters.day
  if (filters.period === 'custom') {
    if (filters.from) query.de = filters.from
    if (filters.to) query.ate = filters.to
  }
  if (filters.userIds.length) query.usuarios = filters.userIds.join(',')
  if (filters.originId) query.origem = filters.originId
  if (filters.statusId) query.status = filters.statusId
  if (filters.staleDays) query.parados = filters.staleDays
  return query
}

export function dailyRange(filters: DailyFilters, now = new Date()): { from: string; to: string } {
  const today = clinicToday(now)
  switch (filters.period) {
    case '7d':
      return { from: shiftDay(today, -6), to: today }
    case '30d':
      return { from: shiftDay(today, -29), to: today }
    case 'custom': {
      const from = filters.from || today
      const to = filters.to || from
      return from <= to ? { from, to } : { from: to, to: from }
    }
    default:
      return { from: filters.day, to: filters.day }
  }
}

export function toDailyParams(filters: DailyFilters, now = new Date()): DailyParams {
  return {
    ...dailyRange(filters, now),
    user_ids: filters.userIds.length ? filters.userIds : undefined,
    origin_ids: filters.originId ? [Number(filters.originId)] : undefined,
    status_ids: filters.statusId ? [Number(filters.statusId)] : undefined,
    stale_days: filters.staleDays ? Number(filters.staleDays) : undefined,
  }
}

export function activeDailyFilterCount(filters: DailyFilters): number {
  return [filters.userIds.length > 0, !!filters.originId, !!filters.statusId, !!filters.staleDays].filter(Boolean).length
}

export type TeamSortKey =
  | 'name'
  | 'registered'
  | 'touched'
  | 'approached'
  | 'replied'
  | 'booked'
  | 'converted'
  | 'cancelled'
  | 'pending_follow_ups'
  | 'checkins'

export function sortTeamRows(rows: readonly DailyTeamRow[], key: TeamSortKey, dir: 'asc' | 'desc'): DailyTeamRow[] {
  const factor = dir === 'asc' ? 1 : -1
  return [...rows].sort((a, b) => {
    if (key === 'name') {
      return a.user.name.localeCompare(b.user.name, 'pt-BR') * factor
    }
    const diff = (a[key] - b[key]) * factor
    return diff !== 0 ? diff : a.user.name.localeCompare(b.user.name, 'pt-BR')
  })
}

/** "Ontem: 3" / "Período anterior: 3" and "Média 7 dias: 1,4/dia" */
export function comparisonHint(days: number, previous: number, average: string): string {
  const label = days === 1 ? 'Ontem' : 'Período anterior'
  return `${label}: ${previous} · média 7 dias: ${average.replace('.', ',')}/dia`
}
