import { describe, expect, it } from 'vitest'
import {
  currentMonthRange,
  isValidPeriod,
  lastDaysRange,
  periodError,
  periodLengthDays,
  presetForRange,
  rangeForPreset,
} from '@/features/metrics/period'

const now = new Date(2026, 8, 6)

describe('metrics period', () => {
  it('defaults the current month from the first to the last day', () => {
    expect(currentMonthRange(now)).toEqual({ from: '2026-09-01', to: '2026-09-30' })
    expect(rangeForPreset('month', now)).toEqual({ from: '2026-09-01', to: '2026-09-30' })
  })

  it('builds inclusive 7d and 30d windows ending today', () => {
    expect(lastDaysRange(7, now)).toEqual({ from: '2026-08-31', to: '2026-09-06' })
    expect(lastDaysRange(30, now)).toEqual({ from: '2026-08-08', to: '2026-09-06' })
  })

  it('detects presets from a from/to pair', () => {
    expect(presetForRange('2026-09-01', '2026-09-30', now)).toBe('month')
    expect(presetForRange('2026-08-31', '2026-09-06', now)).toBe('7d')
    expect(presetForRange('2026-08-08', '2026-09-06', now)).toBe('30d')
    expect(presetForRange('2026-09-01', '2026-09-10', now)).toBe('custom')
  })

  it('rejects inverted and oversized ranges', () => {
    expect(isValidPeriod('2026-09-01', '2026-09-30')).toBe(true)
    expect(isValidPeriod('2026-09-30', '2026-09-01')).toBe(false)
    expect(periodError('2026-09-30', '2026-09-01')).toContain('data final')
    expect(periodLengthDays('2020-01-01', '2026-01-02')).toBeGreaterThan(1825)
    expect(isValidPeriod('2020-01-01', '2026-01-02')).toBe(false)
  })
})
