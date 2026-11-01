import { describe, expect, it } from 'vitest'
import {
  defaultSortDir,
  formatUnits,
  sortProductSales,
  totalUnitsLabel,
} from '@/features/metrics/productSales'
import type { ProductSalesRow } from '@/types/metrics'

function row(name: string, revenue: string, units: string, symbol: string | null = 'U', sales = 1): ProductSalesRow {
  return {
    product_id: name.length,
    product_name: name,
    product_type_id: null,
    product_type_name: null,
    unit_symbol: symbol,
    units,
    revenue,
    sales_count: sales,
    avg_unit_price: (Number(revenue) / Number(units)).toFixed(2),
    share_percent: null,
    list_total: revenue,
    discount_total: '0.00',
    protocol_revenue: '0.00',
  }
}

describe('vendas por produto', () => {
  const rows = [row('Botox', '1500.00', '3'), row('Ácido', '900.00', '1', 'ml', 2), row('Bioestimulador', '900.00', '2')]

  it('sorts by revenue (ties by name) and by other columns', () => {
    expect(sortProductSales(rows, 'revenue', 'desc').map((r) => r.product_name)).toEqual(['Botox', 'Ácido', 'Bioestimulador'])
    expect(sortProductSales(rows, 'units', 'desc').map((r) => r.product_name)).toEqual(['Botox', 'Bioestimulador', 'Ácido'])
    expect(sortProductSales(rows, 'product_name', 'asc').map((r) => r.product_name)).toEqual(['Ácido', 'Bioestimulador', 'Botox'])
    expect(sortProductSales(rows, 'sales_count', 'asc')[2]!.product_name).toBe('Ácido')
    expect(defaultSortDir('product_name')).toBe('asc')
    expect(defaultSortDir('revenue')).toBe('desc')
  })

  it('formats units with the unit of measure and only totals a single unit', () => {
    expect(formatUnits('3.0000', 'U')).toBe('3 U')
    expect(formatUnits('1.5000', 'ml')).toBe('1,5 ml')
    expect(formatUnits('2.0000', null)).toBe('2')
    expect(totalUnitsLabel(rows)).toBe('—')
    expect(totalUnitsLabel([rows[0]!, rows[2]!])).toBe('5 U')
    expect(totalUnitsLabel([])).toBe('—')
  })
})
