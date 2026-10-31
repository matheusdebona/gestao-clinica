import { describe, expect, it } from 'vitest'
import { daysSince, leadStale, staleLabel, tierFor, tierLabel } from '@/features/leads/stale'

const now = new Date('2026-10-31T12:00:00Z')
const tiers = [
  { days: 7, color: 'warning' as const },
  { days: 3, color: 'warning' as const },
  { days: 30, color: 'danger' as const, label: 'Esfriando' },
  { days: 60, color: 'danger' as const },
]

describe('lead parado (tiers)', () => {
  it('counts whole days since the last activity', () => {
    expect(daysSince('2026-10-28T12:00:00Z', now)).toBe(3)
    expect(daysSince('2026-10-28T13:00:00Z', now)).toBe(2)
    expect(daysSince(null, now)).toBe(0)
    expect(daysSince('2026-11-02T00:00:00Z', now)).toBe(0)
  })

  it('picks the highest tier reached', () => {
    expect(tierFor(2, tiers)).toBeNull()
    expect(tierFor(3, tiers)?.days).toBe(3)
    expect(tierFor(29, tiers)?.days).toBe(7)
    expect(tierFor(45, tiers)?.days).toBe(30)
    expect(tierFor(90, tiers)?.days).toBe(60)
    expect(tierFor(90, [])).toBeNull()
  })

  it('only flags open leads', () => {
    const lead = { last_activity_at: '2026-10-23T12:00:00Z', created_at: '2026-10-20T12:00:00Z' }
    expect(leadStale(lead, tiers, now)).toEqual({ days: 8, tier: tiers[0] })
    expect(leadStale({ ...lead, cancelled_at: '2026-10-27T00:00:00Z' }, tiers, now)).toBeNull()
    expect(leadStale({ ...lead, client_id: 4 }, tiers, now)).toBeNull()
    expect(leadStale({ created_at: '2026-10-27T12:00:00Z' }, tiers, now)?.tier.days).toBe(3)
  })

  it('labels in pt-BR', () => {
    expect(staleLabel(1)).toBe('Parado há 1 dia')
    expect(staleLabel(4)).toBe('Parado há 4 dias')
    expect(tierLabel(tiers[2]!)).toBe('Esfriando')
    expect(tierLabel(tiers[0]!)).toBe('Parados há 7+ dias')
  })
})
