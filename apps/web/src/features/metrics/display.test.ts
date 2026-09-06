import { describe, expect, it } from 'vitest'
import { formatBRL, formatIsoDate, formatPercent } from '@/lib/formatters'
import { formatSeriesLabel, maxNumeric, rankRatio, revenueChartPoints } from '@/features/metrics/chart'

function brl(value: string): string {
  return formatBRL(value).replace(/\u00a0|\u202f/g, ' ')
}

describe('metrics display smoke (API fixture strings)', () => {
  it('renders commercial hero numbers from the September fixture', () => {
    // CommercialMetricsTest: revenue 300.00, ticket 150.00, discount 14.29
    expect(brl('300.00')).toBe('R$ 300,00')
    expect(brl('150.00')).toBe('R$ 150,00')
    expect(formatPercent('14.29')).toBe('14,29%')
    expect(formatPercent('50.00')).toBe('50%')
  })

  it('renders acquisition conversion from the lifetime fixture', () => {
    // AcquisitionMetricsTest: 1 of 3 clients converted
    expect(formatPercent('33.33')).toBe('33,33%')
    expect(brl('800.00')).toBe('R$ 800,00')
  })

  it('renders margin percent from the cohort fixture', () => {
    // MarginMetricsTest cohort_sale: 75.96%; period September: 100.00%
    expect(formatPercent('75.96')).toBe('75,96%')
    expect(formatPercent('100.00')).toBe('100%')
    expect(brl('1000.00')).toBe('R$ 1.000,00')
    expect(brl('-300.00')).toBe('-R$ 300,00')
  })

  it('formats ISO dates without UTC day-shift', () => {
    expect(formatIsoDate('2026-09-01')).toBe('01/09/2026')
    expect(formatIsoDate('2026-09-30')).toBe('30/09/2026')
  })

  it('maps revenue series to chart points', () => {
    const chart = revenueChartPoints(
      [
        { period: '2026-09-05', revenue: '200.00', sales_count: 1 },
        { period: '2026-09-10', revenue: '100.00', sales_count: 1 },
      ],
      'day',
    )
    expect(chart.values).toEqual([200, 100])
    expect(chart.labels[0]).toBe(formatSeriesLabel('2026-09-05', 'day'))
    expect(maxNumeric(['200.00', '100.00'])).toBe(200)
    expect(rankRatio('100.00', 200)).toBe(0.5)
  })
})
