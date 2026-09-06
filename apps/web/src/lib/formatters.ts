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
