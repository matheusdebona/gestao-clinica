export type PeriodPreset = '7d' | '30d' | 'month' | 'custom'

export const MAX_PERIOD_DAYS = 1825

export function toIsoDate(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

export function parseIsoDate(value: string): Date {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value)
  if (!match) {
    return new Date(Number.NaN)
  }
  return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
}

export function currentMonthRange(now = new Date()): { from: string; to: string } {
  const from = new Date(now.getFullYear(), now.getMonth(), 1)
  const to = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  return { from: toIsoDate(from), to: toIsoDate(to) }
}

export function lastDaysRange(days: number, now = new Date()): { from: string; to: string } {
  const to = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  const from = new Date(to)
  from.setDate(from.getDate() - (days - 1))
  return { from: toIsoDate(from), to: toIsoDate(to) }
}

export function rangeForPreset(preset: PeriodPreset, now = new Date()): { from: string; to: string } {
  if (preset === '7d') {
    return lastDaysRange(7, now)
  }
  if (preset === '30d') {
    return lastDaysRange(30, now)
  }
  return currentMonthRange(now)
}

export function presetForRange(from: string, to: string, now = new Date()): PeriodPreset {
  const month = currentMonthRange(now)
  if (from === month.from && to === month.to) {
    return 'month'
  }
  const seven = lastDaysRange(7, now)
  if (from === seven.from && to === seven.to) {
    return '7d'
  }
  const thirty = lastDaysRange(30, now)
  if (from === thirty.from && to === thirty.to) {
    return '30d'
  }
  return 'custom'
}

export function periodLengthDays(from: string, to: string): number {
  const start = parseIsoDate(from)
  const end = parseIsoDate(to)
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
    return Number.NaN
  }
  return Math.round((end.getTime() - start.getTime()) / 86_400_000)
}

export function periodError(from: string, to: string): string {
  if (!from || !to) {
    return 'Informe o período.'
  }
  const start = parseIsoDate(from)
  const end = parseIsoDate(to)
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
    return 'Informe um período válido.'
  }
  if (start > end) {
    return 'A data final deve ser igual ou depois da inicial.'
  }
  if (periodLengthDays(from, to) > MAX_PERIOD_DAYS) {
    return 'O período não pode passar de 5 anos.'
  }
  return ''
}

export function isValidPeriod(from: string, to: string): boolean {
  return periodError(from, to) === ''
}
