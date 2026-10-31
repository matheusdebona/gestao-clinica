const DAY_MS = 24 * 60 * 60 * 1000

export const DEFAULT_LEAD_STALE_DAYS = 3

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

/**
 * Days a lead is "parado" (open, no activity for at least `threshold` days), or null when it is not.
 */
export function leadStaleDays(
  lead: {
    client_id?: number | null
    cancelled_at?: string | null
    last_activity_at?: string | null
    created_at?: string | null
  },
  threshold: number,
  now = new Date(),
): number | null {
  if (lead.client_id || lead.cancelled_at) {
    return null
  }
  const days = daysSince(lead.last_activity_at ?? lead.created_at, now)
  return days >= threshold ? days : null
}

export function staleLabel(days: number): string {
  return days === 1 ? 'Parado há 1 dia' : `Parado há ${days} dias`
}
