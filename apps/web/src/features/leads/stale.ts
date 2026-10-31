import type { LeadStatusColor } from '@/types/lead'

const DAY_MS = 24 * 60 * 60 * 1000

export interface FollowUpTierLike {
  days: number
  color: LeadStatusColor
  display_label?: string
  label?: string | null
}

/** Whole days since the given ISO time (0 when in the future or invalid). */
export function daysSince(iso: string | null | undefined, now = new Date()): number {
  if (!iso) {
    return 0
  }
  const time = new Date(iso).getTime()
  if (Number.isNaN(time)) {
    return 0
  }
  return Math.max(0, Math.floor((now.getTime() - time) / DAY_MS))
}

/** Highest tier reached after `days` without contact (tiers in any order), or null. */
export function tierFor<T extends FollowUpTierLike>(days: number, tiers: readonly T[]): T | null {
  let reached: T | null = null
  for (const tier of tiers) {
    if (days >= tier.days && (!reached || tier.days > reached.days)) {
      reached = tier
    }
  }
  return reached
}

/**
 * Lead "parado": open (not converted/cancelled) and without activity for at least the smallest tier.
 */
export function leadStale<T extends FollowUpTierLike>(
  lead: {
    client_id?: number | null
    cancelled_at?: string | null
    last_activity_at?: string | null
    created_at?: string | null
  },
  tiers: readonly T[],
  now = new Date(),
): { days: number; tier: T } | null {
  if (lead.client_id || lead.cancelled_at) {
    return null
  }
  const days = daysSince(lead.last_activity_at ?? lead.created_at, now)
  const tier = tierFor(days, tiers)
  return tier ? { days, tier } : null
}

export function staleLabel(days: number): string {
  return days === 1 ? 'Parado há 1 dia' : `Parado há ${days} dias`
}

export function tierLabel(tier: FollowUpTierLike): string {
  const custom = tier.label?.trim()
  return custom || tier.display_label || `Parados há ${tier.days}+ dias`
}
