import { describe, expect, it } from 'vitest'
import { nextQuickMilestone, quickMilestoneLabel } from '@/features/leads/milestones'

const open = { client_id: null, cancelled_at: null, approached_at: null, replied_at: null }

describe('lead milestones', () => {
  it('suggests Abordado then Respondeu for open leads only', () => {
    expect(nextQuickMilestone(open)).toBe('approached')
    expect(nextQuickMilestone({ ...open, approached_at: '2026-10-31T10:00:00Z' })).toBe('replied')
    expect(
      nextQuickMilestone({ ...open, approached_at: '2026-10-31T10:00:00Z', replied_at: '2026-10-31T11:00:00Z' }),
    ).toBeNull()
    expect(nextQuickMilestone({ ...open, cancelled_at: '2026-10-31T10:00:00Z' })).toBeNull()
    expect(nextQuickMilestone({ ...open, client_id: 3 })).toBeNull()
    expect(quickMilestoneLabel('approached')).toBe('Marcar abordado')
  })
})

import { funnelLine } from '@/features/leads/funnel'

describe('funnel line', () => {
  it('formats the Instagram example', () => {
    expect(
      funnelLine({ approached: 10, replied: 5, booked: 1, reply_rate: '50.00', booking_rate: '20.00', total_rate: '10.00' }),
    ).toMatch(/^10 → 5 \(50(,00)?\s?%\) → 1 \(20(,00)?\s?% · 10(,00)?\s?% do total\)$/)
  })
})
