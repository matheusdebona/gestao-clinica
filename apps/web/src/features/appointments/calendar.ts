export function startOfDay(date: Date): Date {
  const next = new Date(date)
  next.setHours(0, 0, 0, 0)
  return next
}

export function endOfDay(date: Date): Date {
  const next = new Date(date)
  next.setHours(23, 59, 59, 999)
  return next
}

export function addDays(date: Date, amount: number): Date {
  const next = new Date(date)
  next.setDate(next.getDate() + amount)
  return next
}

export function startOfWeekMonday(date: Date): Date {
  const next = startOfDay(date)
  const weekday = next.getDay()
  const diff = weekday === 0 ? -6 : 1 - weekday
  next.setDate(next.getDate() + diff)
  return next
}

export function weekDays(anchor: Date): Date[] {
  const start = startOfWeekMonday(anchor)
  return Array.from({ length: 7 }, (_, index) => addDays(start, index))
}

export function toDateInput(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

export function fromDateInput(value: string): Date {
  const parsed = new Date(`${value}T00:00:00`)
  return Number.isNaN(parsed.getTime()) ? new Date() : parsed
}

export function isSameDay(left: Date, right: Date): boolean {
  return (
    left.getFullYear() === right.getFullYear() &&
    left.getMonth() === right.getMonth() &&
    left.getDate() === right.getDate()
  )
}

export function formatWeekdayDate(date: Date): string {
  return new Intl.DateTimeFormat('pt-BR', {
    weekday: 'short',
    day: 'numeric',
  }).format(date)
}

export function formatDayHeading(date: Date): string {
  return new Intl.DateTimeFormat('pt-BR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  }).format(date)
}
