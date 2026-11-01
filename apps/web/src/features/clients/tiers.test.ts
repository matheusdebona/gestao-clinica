import { describe, expect, it } from 'vitest'
import {
  TIER_FILTER_NONE,
  previewRange,
  rangeLabel,
  sanitizeTierFilter,
  tierFilterQuery,
  tierFilterSummary,
} from './tiers'

const nbsp = (s: string) => s.replace(/\u00a0/g, ' ')

describe('patient tier ranges', () => {
  it('labels closed and open ranges like the backend', () => {
    expect(nbsp(rangeLabel(0, 500001))).toBe('R$ 0,00 a R$ 5.000,00')
    expect(nbsp(rangeLabel(500001, 1000001))).toBe('R$ 5.000,01 a R$ 10.000,00')
    expect(nbsp(rangeLabel(2000001, null))).toBe('Acima de R$ 20.000,00')
    expect(rangeLabel(0, null)).toBe('Qualquer valor')
  })

  it('previews the edited tier against the other tiers', () => {
    const others = [{ min_amount: '0.00' }, { min_amount: '20000.01' }]
    expect(nbsp(previewRange('10000.01', others)!.label)).toBe('R$ 10.000,01 a R$ 20.000,00')
    expect(previewRange('10000.01', others)!.belowLowest).toBeNull()
    expect(previewRange('20000.01', others)).toBeNull()
    expect(previewRange('', others)).toBeNull()
    expect(nbsp(previewRange('1000', [])!.belowLowest!)).toBe('Abaixo de R$ 1.000,00: sem faixa')
  })
})

describe('tier filter', () => {
  const tiers = [
    { id: 1, name: 'Bronze' },
    { id: 2, name: 'Prata' },
  ]

  it('defaults to Todos (no query param)', () => {
    expect(tierFilterQuery([])).toBeUndefined()
    expect(tierFilterSummary([], tiers)).toBe('Todos')
    expect(tierFilterSummary([], [])).toBe('Todos')
  })

  it('serializes tiers and Sem faixa', () => {
    expect(tierFilterQuery([2, TIER_FILTER_NONE])).toBe('2,none')
    expect(tierFilterSummary([2, TIER_FILTER_NONE], tiers)).toBe('Prata, Sem faixa')
  })

  it('drops tiers that no longer exist, so zero tiers falls back to Todos', () => {
    expect(sanitizeTierFilter([1, 9, TIER_FILTER_NONE], tiers)).toEqual([1, TIER_FILTER_NONE])
    expect(sanitizeTierFilter([1, 2], [])).toEqual([])
  })
})
