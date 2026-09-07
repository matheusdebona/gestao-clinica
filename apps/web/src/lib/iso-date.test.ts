import { describe, expect, it } from 'vitest'
import {
  addMonths,
  formatDatetimeLocal,
  minuteOptions,
  monthGrid,
  monthHeading,
  nowDatetimeLocal,
  parseDatetimeLocal,
  parseIsoDate,
  toDatetimeLocal,
  toIsoDate,
  todayIso,
  weekdayHeaders,
} from './iso-date'

describe('iso-date', () => {
  it('round-trips a local calendar date without UTC shift', () => {
    const date = new Date(2026, 8, 5)
    expect(toIsoDate(date)).toBe('2026-09-05')
    expect(parseIsoDate('2026-09-05')?.getDate()).toBe(5)
    expect(parseIsoDate('2026-09-05')?.getMonth()).toBe(8)
  })

  it('rejects impossible calendar days', () => {
    expect(parseIsoDate('2026-02-31')).toBeNull()
    expect(parseIsoDate('not-a-date')).toBeNull()
    expect(parseIsoDate('')).toBeNull()
  })

  it('builds a Monday-start 6-week grid', () => {
    const cells = monthGrid(2026, 8)
    expect(cells).toHaveLength(42)
    expect(cells[0]?.iso).toBe('2026-08-31')
    expect(cells[0]?.inMonth).toBe(false)
    expect(cells[5]?.iso).toBe('2026-09-05')
    expect(cells[5]?.inMonth).toBe(true)
  })

  it('advances months from the first of the month', () => {
    expect(toIsoDate(addMonths(new Date(2026, 8, 1), 1))).toBe('2026-10-01')
    expect(toIsoDate(addMonths(new Date(2026, 0, 1), -1))).toBe('2025-12-01')
  })

  it('returns PT-BR weekday headers starting on Monday', () => {
    const headers = weekdayHeaders('pt-BR')
    expect(headers).toHaveLength(7)
    expect(headers[0]?.long.toLowerCase()).toContain('segunda')
    expect(headers[6]?.long.toLowerCase()).toContain('domingo')
  })

  it('todayIso matches the local calendar day', () => {
    const now = new Date(2026, 8, 6, 23, 15)
    expect(todayIso(now)).toBe('2026-09-06')
  })

  it('formats a capitalized PT-BR month heading', () => {
    expect(monthHeading(new Date(2026, 8, 1))).toMatch(/^Setembro de 2026$/i)
  })

  it('round-trips datetime-local without UTC shift', () => {
    expect(toDatetimeLocal('2026-09-08', 14, 30)).toBe('2026-09-08T14:30')
    expect(parseDatetimeLocal('2026-09-08T14:30')).toEqual({
      dateIso: '2026-09-08',
      hours: 14,
      minutes: 30,
    })
    expect(parseDatetimeLocal('2026-02-31T10:00')).toBeNull()
    expect(formatDatetimeLocal('2026-09-08T14:30')).toMatch(/08\/09\/2026/)
    expect(formatDatetimeLocal('2026-09-08T14:30')).toMatch(/14:30/)
  })

  it('snaps nowDatetimeLocal to a 5-minute step', () => {
    expect(nowDatetimeLocal(new Date(2026, 8, 8, 14, 32), 5)).toBe('2026-09-08T14:30')
    expect(nowDatetimeLocal(new Date(2026, 8, 8, 14, 58), 5)).toBe('2026-09-08T15:00')
  })

  it('keeps an off-step minute in the picker list', () => {
    expect(minuteOptions(32)).toContain(32)
    expect(minuteOptions(30)).not.toContain(32)
  })
})
