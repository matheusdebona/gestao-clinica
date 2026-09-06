import { describe, expect, it } from 'vitest'
import { formatRelativeTime } from '@/lib/formatters'

const now = new Date('2026-09-06T18:00:00.000Z')

describe('formatRelativeTime', () => {
  it('uses compact Portuguese relative copy', () => {
    expect(formatRelativeTime('2026-09-06T17:59:30.000Z', now)).toBe('agora')
    expect(formatRelativeTime('2026-09-06T17:55:00.000Z', now)).toMatch(/5 minutos/)
    expect(formatRelativeTime('2026-09-06T16:00:00.000Z', now)).toMatch(/2 horas/)
  })

  it('falls back to a date after a week', () => {
    const label = formatRelativeTime('2026-08-01T18:00:00.000Z', now)
    expect(label).toMatch(/\d{2}\/\d{2}\/\d{4}/)
  })

  it('returns an em dash for invalid values', () => {
    expect(formatRelativeTime(null)).toBe('—')
    expect(formatRelativeTime('not-a-date')).toBe('—')
  })
})
