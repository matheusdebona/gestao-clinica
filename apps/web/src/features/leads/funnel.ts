import { formatPercent } from '@/lib/formatters'

export interface LeadFunnelRow {
  id: number | null
  key: string
  label: string
  approached: number
  replied: number
  booked: number
  reply_rate: string | null
  booking_rate: string | null
  total_rate: string | null
}

export interface LeadFunnel {
  total: LeadFunnelRow
  by_origin: LeadFunnelRow[]
  by_user: LeadFunnelRow[]
}

/** "10 → 5 (50%) → 1 (20% · 10% do total)" */
export function funnelLine(row: Pick<LeadFunnelRow, 'approached' | 'replied' | 'booked' | 'reply_rate' | 'booking_rate' | 'total_rate'>): string {
  return `${row.approached} → ${row.replied} (${formatPercent(row.reply_rate)}) → ${row.booked} (${formatPercent(row.booking_rate)} · ${formatPercent(row.total_rate)} do total)`
}
