import { describe, expect, it } from 'vitest'
import {
  activeDailyFilterCount,
  clinicToday,
  comparisonHint,
  dailyFiltersFromQuery,
  dailyFiltersToQuery,
  dailyRange,
  shiftDay,
  sortTeamRows,
  toDailyParams,
} from '@/features/daily/filters'
import type { DailyTeamRow } from '@/features/daily/api'

// 01:30 UTC on Nov 1 is still Oct 31 in São Paulo.
const now = new Date('2026-11-01T01:30:00Z')

describe('daily filters', () => {
  it('uses the São Paulo calendar day', () => {
    expect(clinicToday(now)).toBe('2026-10-31')
    expect(shiftDay('2026-11-01', -1)).toBe('2026-10-31')
  })

  it('round-trips through the URL', () => {
    const f = dailyFiltersFromQuery(
      { aba: 'equipe', periodo: '7d', usuarios: '3,5', origem: '2', status: '4', parados: '7' },
      now,
    )
    expect(f).toMatchObject({ tab: 'equipe', period: '7d', userIds: [3, 5], originId: '2', statusId: '4', staleDays: '7' })
    expect(dailyFiltersToQuery(f, now)).toEqual({ aba: 'equipe', periodo: '7d', usuarios: '3,5', origem: '2', status: '4', parados: '7' })
    expect(toDailyParams(f, now)).toMatchObject({ from: '2026-10-25', to: '2026-10-31', user_ids: [3, 5], origin_ids: [2], status_ids: [4], stale_days: 7 })
    expect(activeDailyFilterCount(f)).toBe(4)
    expect(dailyFiltersToQuery(dailyFiltersFromQuery({}, now), now)).toEqual({})
  })

  it('supports a chosen day and custom ranges', () => {
    expect(dailyRange(dailyFiltersFromQuery({ data: '2026-10-20' }, now), now)).toEqual({ from: '2026-10-20', to: '2026-10-20' })
    expect(dailyRange(dailyFiltersFromQuery({ periodo: 'custom', de: '2026-10-10', ate: '2026-10-01' }, now), now)).toEqual({
      from: '2026-10-01',
      to: '2026-10-10',
    })
  })

  it('sorts the team table by any column', () => {
    const row = (name: string, touched: number) => ({ user: { id: touched, name }, touched }) as unknown as DailyTeamRow
    const rows = [row('Bruno', 2), row('Ana', 5), row('Carla', 2)]
    expect(sortTeamRows(rows, 'touched', 'desc').map((r) => r.user.name)).toEqual(['Ana', 'Bruno', 'Carla'])
    expect(sortTeamRows(rows, 'name', 'asc').map((r) => r.user.name)).toEqual(['Ana', 'Bruno', 'Carla'])
    expect(sortTeamRows(rows, 'touched', 'asc')[2]!.user.name).toBe('Ana')
  })

  it('formats the comparison hint', () => {
    expect(comparisonHint(1, 3, '1.4')).toBe('Ontem: 3 · média 7 dias: 1,4/dia')
    expect(comparisonHint(7, 3, '0.0')).toBe('Período anterior: 3 · média 7 dias: 0,0/dia')
  })
})
