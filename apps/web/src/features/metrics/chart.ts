import { parseIsoDate } from '@/features/metrics/period'
import type { MetricsGranularity, RevenueSeriesPoint } from '@/types/metrics'

export function formatSeriesLabel(period: string, granularity: MetricsGranularity): string {
  const date = parseIsoDate(period)
  if (Number.isNaN(date.getTime())) {
    return period
  }
  if (granularity === 'month') {
    return new Intl.DateTimeFormat('pt-BR', { month: 'short', year: '2-digit' }).format(date)
  }
  if (granularity === 'week') {
    const day = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit' }).format(date)
    return `sem. ${day}`
  }
  return new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit' }).format(date)
}

export function revenueChartPoints(
  series: RevenueSeriesPoint[],
  granularity: MetricsGranularity,
): { labels: string[]; values: number[] } {
  return {
    labels: series.map((row) => formatSeriesLabel(row.period, granularity)),
    values: series.map((row) => Number(row.revenue)),
  }
}

export function rankRatio(value: string | number, max: number): number {
  if (max <= 0) {
    return 0
  }
  return Math.max(0, Number(value) || 0) / max
}

export function maxNumeric(values: Array<string | number>): number {
  return values.reduce<number>((max, value) => Math.max(max, Number(value) || 0), 0)
}
