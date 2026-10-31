import { describe, expect, it } from 'vitest'
import { daysSince, leadStaleDays, staleLabel } from '@/features/leads/stale'

const now = new Date('2026-10-31T12:00:00Z')

describe('lead parado', () => {
  it('counts whole days since the last activity', () => {
    expect(daysSince('2026-10-28T12:00:00Z', now)).toBe(3)
    expect(daysSince('2026-10-28T13:00:00Z', now)).toBe(2)
    expect(daysSince(null, now)).toBe(0)
    expect(daysSince('2026-11-02T00:00:00Z', now)).toBe(0)
  })

  it('only flags open leads past the threshold', () => {
    const lead = { last_activity_at: '2026-10-26T12:00:00Z', created_at: '2026-10-20T12:00:00Z' }
    expect(leadStaleDays(lead, 3, now)).toBe(5)
    expect(leadStaleDays(lead, 7, now)).toBeNull()
    expect(leadStaleDays({ ...lead, cancelled_at: '2026-10-27T00:00:00Z' }, 3, now)).toBeNull()
    expect(leadStaleDays({ ...lead, client_id: 4 }, 3, now)).toBeNull()
    expect(leadStaleDays({ created_at: '2026-10-27T12:00:00Z' }, 3, now)).toBe(4)
  })

  it('labels in pt-BR', () => {
    expect(staleLabel(1)).toBe('Parado há 1 dia')
    expect(staleLabel(4)).toBe('Parado há 4 dias')
  })
})
