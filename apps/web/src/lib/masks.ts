import { emptyToMoney } from '@/lib/formatters'

const MAX_PHONE_DIGITS = 11
const MAX_MONEY_DIGITS = 11

export function phoneDigits(value: string | number | null | undefined): string {
  let digits = String(value ?? '').replace(/\D/g, '')
  if (digits.startsWith('55') && digits.length >= 12 && digits.length <= 13) {
    digits = digits.slice(2)
  }
  return digits.slice(0, MAX_PHONE_DIGITS)
}

export function formatPhoneBR(value: string | number | null | undefined): string {
  const digits = phoneDigits(value)
  if (!digits) {
    return ''
  }
  if (digits.length <= 2) {
    return `(${digits}`
  }
  if (digits.length <= 6) {
    return `(${digits.slice(0, 2)}) ${digits.slice(2)}`
  }
  if (digits.length <= 10) {
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`
  }
  return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`
}

export function decimalFromMoneyInput(raw: string): string {
  const digits = raw.replace(/\D/g, '').slice(0, MAX_MONEY_DIGITS)
  if (!digits || /^0+$/.test(digits)) {
    return ''
  }
  const clipped = digits.replace(/^0+/, '') || '0'
  const padded = clipped.padStart(3, '0')
  const intPart = padded.slice(0, -2).replace(/^0+(?=\d)/, '')
  const fraction = padded.slice(-2)
  return `${intPart}.${fraction}`
}

export function formatMoneyAmount(value: string | number | null | undefined): string {
  if (value === null || value === undefined || String(value).trim() === '') {
    return ''
  }
  const normalized = typeof value === 'number' ? value.toFixed(2) : emptyToMoney(String(value))
  if (normalized === null) {
    return ''
  }
  const amount = Number(normalized)
  if (!Number.isFinite(amount)) {
    return ''
  }
  return new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount)
}

export function omitValueListeners(attrs: Record<string, unknown>): Record<string, unknown> {
  const { onInput: _onInput, onChange: _onChange, value: _value, ...rest } = attrs
  return rest
}
