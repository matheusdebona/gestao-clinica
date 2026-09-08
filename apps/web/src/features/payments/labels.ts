export const PAYMENT_KIND_LABELS: Record<string, string> = {
  cash: 'Dinheiro',
  pix: 'PIX',
  check: 'Cheque',
  credit_card: 'Cartão de crédito',
  debit_card: 'Cartão de débito',
  boleto: 'Boleto',
  other: 'Outros',
}

export const PAYMENT_KIND_OPTIONS = [
  { value: 'cash', label: 'Dinheiro' },
  { value: 'pix', label: 'PIX' },
  { value: 'debit_card', label: 'Cartão de débito' },
  { value: 'credit_card', label: 'Cartão de crédito' },
  { value: 'boleto', label: 'Boleto' },
  { value: 'check', label: 'Cheque' },
  { value: 'other', label: 'Outros' },
]

export const CARD_KINDS = new Set(['credit_card', 'debit_card'])

export function paymentKindLabel(kind: string): string {
  return PAYMENT_KIND_LABELS[kind] ?? kind
}

export function formatFeePercent(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') {
    return '—'
  }
  const amount = typeof value === 'number' ? value : Number(value)
  if (!Number.isFinite(amount)) {
    return '—'
  }
  return `${new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 4 }).format(amount)}%`
}

export function slugifyCode(name: string): string {
  return name
    .normalize('NFD')
    .replace(/\p{M}/gu, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .slice(0, 50)
}
