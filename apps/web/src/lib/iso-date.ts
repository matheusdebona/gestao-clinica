export const ISO_DATE_RE = /^(\d{4})-(\d{2})-(\d{2})$/
export const DATETIME_LOCAL_RE = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::\d{2})?$/

export interface DatetimeLocalParts {
  dateIso: string
  hours: number
  minutes: number
}

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

export function parseDatetimeLocal(value: string): DatetimeLocalParts | null {
  const match = DATETIME_LOCAL_RE.exec(value.trim())
  if (!match) {
    return null
  }
  const dateIso = `${match[1]}-${match[2]}-${match[3]}`
  if (!parseIsoDate(dateIso)) {
    return null
  }
  const hours = Number(match[4])
  const minutes = Number(match[5])
  if (hours > 23 || minutes > 59) {
    return null
  }
  return { dateIso, hours, minutes }
}

export function toDatetimeLocal(dateIso: string, hours: number, minutes: number): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${dateIso}T${pad(hours)}:${pad(minutes)}`
}

export function nowDatetimeLocal(now = new Date(), minuteStep = 5): string {
  const step = minuteStep > 0 ? minuteStep : 1
  let hours = now.getHours()
  let minutes = Math.round(now.getMinutes() / step) * step
  const date = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  if (minutes >= 60) {
    minutes = 0
    hours += 1
  }
  if (hours >= 24) {
    hours = 0
    date.setDate(date.getDate() + 1)
  }
  return toDatetimeLocal(toIsoDate(date), hours, minutes)
}

export function formatDatetimeLocal(value: string, locale = 'pt-BR'): string {
  const parsed = parseDatetimeLocal(value)
  if (!parsed) {
    return ''
  }
  const date = parseIsoDate(parsed.dateIso)
  if (!date) {
    return ''
  }
  date.setHours(parsed.hours, parsed.minutes, 0, 0)
  return new Intl.DateTimeFormat(locale, { dateStyle: 'short', timeStyle: 'short' }).format(date)
}

export function minuteOptions(selected: number, step = 5): number[] {
  const base = Array.from({ length: Math.floor(60 / step) }, (_, index) => index * step)
  if (Number.isInteger(selected) && selected >= 0 && selected < 60 && !base.includes(selected)) {
    return [...base, selected].sort((left, right) => left - right)
  }
  return base
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
