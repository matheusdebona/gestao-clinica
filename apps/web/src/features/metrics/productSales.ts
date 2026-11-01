import { formatQty } from '@/lib/formatters'
import type { ProductSalesRow } from '@/types/metrics'

export type ProductSalesSortKey = 'product_name' | 'units' | 'sales_count' | 'avg_unit_price' | 'revenue' | 'share_percent'
export type SortDir = 'asc' | 'desc'

export const PRODUCT_SALES_COLUMNS: { key: ProductSalesSortKey; label: string; numeric: boolean }[] = [
  { key: 'product_name', label: 'Produto', numeric: false },
  { key: 'units', label: 'Unidades', numeric: true },
  { key: 'sales_count', label: 'Vendas', numeric: true },
  { key: 'avg_unit_price', label: 'Preço médio', numeric: true },
  { key: 'revenue', label: 'Faturamento', numeric: true },
  { key: 'share_percent', label: '% do total', numeric: true },
]

export const PRODUCT_SALES_SORT_OPTIONS = [
  { value: 'revenue', label: 'Maior faturamento' },
  { value: 'units', label: 'Mais unidades' },
  { value: 'sales_count', label: 'Mais vendas' },
  { value: 'avg_unit_price', label: 'Maior preço médio' },
  { value: 'product_name', label: 'Nome (A–Z)' },
]

/** Ordena as linhas; empate por faturamento e depois pelo nome. */
export function sortProductSales(
  rows: ProductSalesRow[],
  key: ProductSalesSortKey,
  dir: SortDir,
): ProductSalesRow[] {
  const sign = dir === 'asc' ? 1 : -1
  const num = (row: ProductSalesRow, k: ProductSalesSortKey) => Number(row[k] ?? 0) || 0
  return [...rows].sort((a, b) => {
    const primary =
      key === 'product_name'
        ? a.product_name.localeCompare(b.product_name, 'pt-BR')
        : num(a, key) - num(b, key)
    if (primary !== 0) return primary * sign
    return num(b, 'revenue') - num(a, 'revenue') || a.product_name.localeCompare(b.product_name, 'pt-BR')
  })
}

/** Direção padrão ao escolher uma coluna: nome A–Z, números do maior para o menor. */
export function defaultSortDir(key: ProductSalesSortKey): SortDir {
  return key === 'product_name' ? 'asc' : 'desc'
}

/** "3 U", "1,5 ml" (sem unidade: só o número). */
export function formatUnits(units: string | number, symbol: string | null | undefined): string {
  const value = formatQty(units)
  return symbol ? `${value} ${symbol}` : value
}

/** Soma de unidades só faz sentido com a mesma unidade em todas as linhas. */
export function totalUnitsLabel(rows: ProductSalesRow[]): string {
  if (!rows.length) return '—'
  const symbols = new Set(rows.map((row) => row.unit_symbol ?? ''))
  if (symbols.size !== 1) return '—'
  const total = rows.reduce((sum, row) => sum + (Number(row.units) || 0), 0)
  return formatUnits(total, rows[0]!.unit_symbol)
}
