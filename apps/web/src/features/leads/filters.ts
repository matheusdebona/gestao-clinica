import type { LocationQuery, LocationQueryRaw } from 'vue-router'
import { lastDaysRange, currentMonthRange, toIsoDate } from '@/features/metrics/period'
import type { LeadListParams } from '@/features/leads/api'

export type LeadPeriod = 'all' | 'today' | '7d' | '30d' | 'month' | 'custom'
export type LeadConvertedFilter = 'all' | 'open' | 'converted'

export interface LeadFilters {
  q: string
  period: LeadPeriod
  from: string
  to: string
  statusIds: number[]
  originId: string
  createdBy: string
  converted: LeadConvertedFilter
  page: number
}

export const LEAD_PERIOD_ITEMS: { value: LeadPeriod; label: string }[] = [
  { value: 'all', label: 'Tudo' },
  { value: 'today', label: 'Hoje' },
  { value: '7d', label: '7 dias' },
  { value: '30d', label: '30 dias' },
  { value: 'month', label: 'Este mês' },
  { value: 'custom', label: 'Período' },
]

export const LEAD_CONVERTED_ITEMS: { value: LeadConvertedFilter; label: string }[] = [
  { value: 'all', label: 'Todos' },
  { value: 'open', label: 'Não convertidos' },
  { value: 'converted', label: 'Viraram paciente' },
]

const PERIODS = new Set<LeadPeriod>(['all', 'today', '7d', '30d', 'month', 'custom'])
const ISO = /^\d{4}-\d{2}-\d{2}$/

export const emptyLeadFilters = (): LeadFilters => ({
  q: '',
  period: 'all',
  from: '',
  to: '',
  statusIds: [],
  originId: '',
  createdBy: '',
  converted: 'all',
  page: 1,
})

function str(value: LocationQuery[string]): string {
  const raw = Array.isArray(value) ? value[0] : value
  return typeof raw === 'string' ? raw : ''
}

function positiveInt(value: string): string {
  return /^\d+$/.test(value) && Number(value) > 0 ? value : ''
}

export function filtersFromQuery(query: LocationQuery): LeadFilters {
  const filters = emptyLeadFilters()
  filters.q = str(query.q)
  const period = str(query.periodo) as LeadPeriod
  filters.period = PERIODS.has(period) ? period : 'all'
  if (filters.period === 'custom') {
    filters.from = ISO.test(str(query.de)) ? str(query.de) : ''
    filters.to = ISO.test(str(query.ate)) ? str(query.ate) : ''
  }
  filters.statusIds = str(query.status)
    .split(',')
    .map((part) => Number(part))
    .filter((id) => Number.isInteger(id) && id > 0)
  filters.originId = positiveInt(str(query.origem))
  filters.createdBy = positiveInt(str(query.por))
  const converted = str(query.convertidos)
  filters.converted = converted === 'nao' ? 'open' : converted === 'sim' ? 'converted' : 'all'
  const page = Number(str(query.pagina))
  filters.page = Number.isInteger(page) && page > 1 ? page : 1
  return filters
}

export function filtersToQuery(filters: LeadFilters): LocationQueryRaw {
  const query: LocationQueryRaw = {}
  if (filters.q) {
    query.q = filters.q
  }
  if (filters.period !== 'all') {
    query.periodo = filters.period
  }
  if (filters.period === 'custom') {
    if (filters.from) {
      query.de = filters.from
    }
    if (filters.to) {
      query.ate = filters.to
    }
  }
  if (filters.statusIds.length) {
    query.status = filters.statusIds.join(',')
  }
  if (filters.originId) {
    query.origem = filters.originId
  }
  if (filters.createdBy) {
    query.por = filters.createdBy
  }
  if (filters.converted === 'open') {
    query.convertidos = 'nao'
  } else if (filters.converted === 'converted') {
    query.convertidos = 'sim'
  }
  if (filters.page > 1) {
    query.pagina = String(filters.page)
  }
  return query
}

export function periodRange(filters: LeadFilters, now = new Date()): { from?: string; to?: string } {
  switch (filters.period) {
    case 'today': {
      const today = toIsoDate(now)
      return { from: today, to: today }
    }
    case '7d':
      return lastDaysRange(7, now)
    case '30d':
      return lastDaysRange(30, now)
    case 'month':
      return currentMonthRange(now)
    case 'custom':
      return { from: filters.from || undefined, to: filters.to || undefined }
    default:
      return {}
  }
}

/** Filters inside the sheet (search is separate and not counted). */
export function activeFilterCount(filters: LeadFilters): number {
  let count = 0
  if (filters.period !== 'all') {
    count++
  }
  if (filters.statusIds.length) {
    count++
  }
  if (filters.originId) {
    count++
  }
  if (filters.createdBy) {
    count++
  }
  if (filters.converted !== 'all') {
    count++
  }
  return count
}

export function toLeadListParams(filters: LeadFilters, now = new Date()): LeadListParams {
  const range = periodRange(filters, now)
  return {
    q: filters.q || undefined,
    page: filters.page,
    lead_status_ids: filters.statusIds.length ? filters.statusIds : undefined,
    client_origin_id: filters.originId ? Number(filters.originId) : undefined,
    created_by_user_id: filters.createdBy ? Number(filters.createdBy) : undefined,
    converted: filters.converted === 'all' ? undefined : filters.converted === 'converted',
    from: range.from,
    to: range.to,
  }
}
