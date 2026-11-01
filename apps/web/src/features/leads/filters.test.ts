import { describe, expect, it } from 'vitest'
import {
  activeFilterCount,
  emptyLeadFilters,
  filtersFromQuery,
  filtersToQuery,
  toLeadListParams,
} from '@/features/leads/filters'

describe('lead filters', () => {
  it('round-trips through the URL query', () => {
    const filters = {
      ...emptyLeadFilters(),
      q: 'ana',
      period: 'custom' as const,
      from: '2026-10-01',
      to: '2026-10-31',
      statusIds: [3, 5],
      originId: '7',
      createdBy: '2',
      converted: 'open' as const,
      page: 2,
    }
    const query = filtersToQuery(filters)
    expect(query).toEqual({
      q: 'ana',
      periodo: 'custom',
      de: '2026-10-01',
      ate: '2026-10-31',
      status: '3,5',
      origem: '7',
      por: '2',
      convertidos: 'nao',
      pagina: '2',
    })
    expect(filtersFromQuery(query as Record<string, string>)).toEqual(filters)
    expect(activeFilterCount(filters)).toBe(5)
  })

  it('ignores junk and maps presets to a date range', () => {
    const filters = filtersFromQuery({ periodo: '7d', status: 'x,0,4', origem: 'abc', pagina: '-1' })
    expect(filters.statusIds).toEqual([4])
    expect(filters.originId).toBe('')
    expect(filters.page).toBe(1)
    const params = toLeadListParams(filters, new Date('2026-10-31T12:00:00-03:00'))
    expect(params.from).toBe('2026-10-25')
    expect(params.to).toBe('2026-10-31')
    expect(params.lead_status_ids).toEqual([4])
    expect(activeFilterCount(emptyLeadFilters())).toBe(0)
  })
})

describe('lead filters: parados', () => {
  it('maps parados=<dias> / parados=todos to the API and counts it', () => {
    const tier = filtersFromQuery({ parados: '7' })
    expect(tier.stale).toBe('7')
    expect(filtersToQuery(tier)).toEqual({ parados: '7' })
    expect(toLeadListParams(tier).stale_days).toBe(7)
    expect(activeFilterCount(tier)).toBe(1)
    const any = filtersFromQuery({ parados: 'todos' })
    expect(toLeadListParams(any)).toMatchObject({ stale: true, stale_days: undefined })
    expect(filtersToQuery(any)).toEqual({ parados: 'todos' })
    expect(filtersFromQuery({ parados: 'x' }).stale).toBe('')
    expect(toLeadListParams(emptyLeadFilters()).stale).toBeUndefined()
  })
})
