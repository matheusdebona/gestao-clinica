import type { TaxRegime } from '@/types/finance'

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
