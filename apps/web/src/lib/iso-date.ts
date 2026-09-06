export const ISO_DATE_RE = /^(\d{4})-(\d{2})-(\d{2})$/

export interface CalendarCell {
  iso: string
  day: number
  inMonth: boolean
}

export interface WeekdayHeader {
  short: string
  long: string
}

const WEEKDAY_REF_MONDAY = new Date(2024, 0, 1)

export function toIsoDate(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

export function parseIsoDate(value: string): Date | null {
  const match = ISO_DATE_RE.exec(value.trim())
  if (!match) {
    return null
  }
  const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
  if (
    date.getFullYear() !== Number(match[1]) ||
    date.getMonth() !== Number(match[2]) - 1 ||
    date.getDate() !== Number(match[3])
  ) {
    return null
  }
  return date
}

export function todayIso(now = new Date()): string {
  return toIsoDate(now)
}

export function startOfMonth(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth(), 1)
}

export function addMonths(date: Date, amount: number): Date {
  return new Date(date.getFullYear(), date.getMonth() + amount, 1)
}

export function weekdayHeaders(locale = 'pt-BR'): WeekdayHeader[] {
  const shortFmt = new Intl.DateTimeFormat(locale, { weekday: 'narrow' })
  const longFmt = new Intl.DateTimeFormat(locale, { weekday: 'long' })
  return Array.from({ length: 7 }, (_, index) => {
    const day = new Date(WEEKDAY_REF_MONDAY)
    day.setDate(WEEKDAY_REF_MONDAY.getDate() + index)
    return {
      short: shortFmt.format(day).replace('.', '').slice(0, 1).toUpperCase(),
      long: longFmt.format(day),
    }
  })
}

export function monthHeading(date: Date, locale = 'pt-BR'): string {
  const label = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(date)
  return label.charAt(0).toUpperCase() + label.slice(1)
}

export function monthGrid(year: number, monthIndex: number): CalendarCell[] {
  const first = new Date(year, monthIndex, 1)
  const weekday = first.getDay()
  const mondayOffset = weekday === 0 ? 6 : weekday - 1
  const start = new Date(year, monthIndex, 1 - mondayOffset)
  return Array.from({ length: 42 }, (_, index) => {
    const date = new Date(start.getFullYear(), start.getMonth(), start.getDate() + index)
    return {
      iso: toIsoDate(date),
      day: date.getDate(),
      inMonth: date.getMonth() === monthIndex,
    }
  })
}
