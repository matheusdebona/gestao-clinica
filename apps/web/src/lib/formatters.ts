export function formatBRL(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') {
    return '—'
  }
  const amount = typeof value === 'number' ? value : Number(value)
  if (!Number.isFinite(amount)) {
    return '—'
  }
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(amount)
}

export function emptyToNull(value: string): string | null {
  const trimmed = value.trim()
  return trimmed === '' ? null : trimmed
}

export function emptyToInt(value: string): number | null {
  const trimmed = value.trim()
  if (trimmed === '') {
    return null
  }
  const parsed = Number(trimmed)
  return Number.isInteger(parsed) ? parsed : null
}

export function emptyToMoney(value: string): string | null {
  const trimmed = value.trim().replace(/\s/g, '')
  if (!trimmed) {
    return null
  }
  if (trimmed.includes(',') && trimmed.includes('.')) {
    return trimmed.replace(/\./g, '').replace(',', '.')
  }
  if (trimmed.includes(',')) {
    return trimmed.replace(',', '.')
  }
  return trimmed
}

export function formatQty(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') {
    return '—'
  }
  const amount = typeof value === 'number' ? value : Number(value)
  if (!Number.isFinite(amount)) {
    return '—'
  }
  return new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 4 }).format(amount)
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) {
    return '—'
  }
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) {
    return '—'
  }
  return new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(parsed)
}

export function formatTime(value: string | null | undefined): string {
  if (!value) {
    return '—'
  }
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) {
    return '—'
  }
  return new Intl.DateTimeFormat('pt-BR', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(parsed)
}

export function formatDate(value: string | null | undefined): string {
  if (!value) {
    return '—'
  }
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) {
    return '—'
  }
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short' }).format(parsed)
}

export function formatIsoDate(value: string | null | undefined): string {
  if (!value) {
    return '—'
  }
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value)
  if (!match) {
    return formatDate(value)
  }
  const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
  if (Number.isNaN(date.getTime())) {
    return '—'
  }
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short' }).format(date)
}

export function formatPercent(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') {
    return '—'
  }
  const amount = typeof value === 'number' ? value : Number(value)
  if (!Number.isFinite(amount)) {
    return '—'
  }
  return `${new Intl.NumberFormat('pt-BR', {
    maximumFractionDigits: 2,
    minimumFractionDigits: 0,
  }).format(amount)}%`
}

export function formatRelativeTime(value: string | null | undefined, now = new Date()): string {
  if (!value) {
    return '—'
  }
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) {
    return '—'
  }

  const diffMs = parsed.getTime() - now.getTime()
  const absMs = Math.abs(diffMs)
  const minute = 60_000
  const hour = 60 * minute
  const day = 24 * hour

  if (absMs < minute) {
    return 'agora'
  }

  const rtf = new Intl.RelativeTimeFormat('pt-BR', { numeric: 'auto' })
  if (absMs < hour) {
    return rtf.format(Math.round(diffMs / minute), 'minute')
  }
  if (absMs < day) {
    return rtf.format(Math.round(diffMs / hour), 'hour')
  }
  if (absMs < 7 * day) {
    return rtf.format(Math.round(diffMs / day), 'day')
  }

  return formatDate(value)
}
