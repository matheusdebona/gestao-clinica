import type { ExpenseKind, TaxRegime } from '@/types/finance'

export const REGIME_LABELS: Record<TaxRegime, string> = {
  simples: 'Simples Nacional',
  presumido: 'Lucro Presumido',
  real: 'Lucro Real',
  mei: 'MEI',
  none: 'Sem imposto sobre a receita',
}

export const TAX_FIELDS_BY_REGIME: Record<TaxRegime, string[]> = {
  simples: ['simples_rate_percent', 'other_percent'],
  presumido: [
    'iss_percent',
    'pis_percent',
    'cofins_percent',
    'irpj_percent',
    'csll_percent',
    'other_percent',
  ],
  real: [
    'iss_percent',
    'pis_percent',
    'cofins_percent',
    'irpj_percent',
    'csll_percent',
    'other_percent',
  ],
  mei: ['other_percent'],
  none: [],
}

const MONTHS = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez']

/** "2026-07" → "jul/2026". */
export function formatMonthLabel(month: string | null | undefined): string {
  if (!month || !/^\d{4}-\d{2}/.test(month)) return '—'
  const [year, m] = month.split('-')
  return `${MONTHS[Number(m) - 1] ?? m}/${year}`
}

export const EXPENSE_KIND_LABELS: Record<ExpenseKind, string> = {
  fixed: 'Fixas',
  personnel: 'Pessoal e pró-labore',
  marketing: 'Marketing e mídia',
  taxes: 'Impostos e taxas',
  financial: 'Financeiras',
  depreciation: 'Depreciação',
  variable: 'Variáveis',
}

export const EXPENSE_KIND_ORDER: ExpenseKind[] = [
  'fixed',
  'personnel',
  'marketing',
  'taxes',
  'financial',
  'depreciation',
  'variable',
]

export const EXPENSE_KIND_OPTIONS = EXPENSE_KIND_ORDER.map((value) => ({
  value,
  label: EXPENSE_KIND_LABELS[value],
}))

export function shiftMonth(month: string, delta: number): string {
  const [y, m] = month.split('-').map(Number)
  const d = new Date(y!, m! - 1 + delta, 1)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}
