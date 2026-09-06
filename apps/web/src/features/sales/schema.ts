import { emptyToMoney } from '@/lib/formatters'
import type { Product } from '@/types/product'
import type { Sale, SaleItem, SaleItemPayload, SalePayment, SalePaymentPayload } from '@/types/sale'

export interface SaleItemDraft {
  product_id: number
  quantity: string
  unit_price: string
  source_protocol_id: number | null
  product_name: string
  product?: Product | null
  min_unit_price?: string
  is_below_minimum?: boolean
}

export interface SalePaymentDraft {
  key: string
  payment_method_id: string
  amount: string
  card_operator_id: string
  card_brand_id: string
  installments: string
}

export function parseQuantity(value: string): number {
  const parsed = Number(emptyToMoney(value) ?? '')
  return Number.isFinite(parsed) ? parsed : NaN
}

export function parseMoneyAmount(value: string): number {
  const parsed = Number(emptyToMoney(value) ?? '')
  return Number.isFinite(parsed) ? parsed : NaN
}

export function money2(value: number): string {
  return value.toFixed(2)
}

export function qtyInput(value: string | number): string {
  const amount = typeof value === 'number' ? value : Number(value)
  if (!Number.isFinite(amount)) {
    return ''
  }
  return String(amount)
}

export function saleItemsToDrafts(items: SaleItem[] | undefined): SaleItemDraft[] {
  return (items ?? []).map((item) => ({
    product_id: item.product_id,
    quantity: qtyInput(item.quantity),
    unit_price: item.unit_price,
    source_protocol_id: item.source_protocol_id,
    product_name: item.product_name,
    product: item.product ?? null,
    min_unit_price: item.min_unit_price,
    is_below_minimum: item.is_below_minimum,
  }))
}

export function draftsToItemPayloads(items: SaleItemDraft[]): SaleItemPayload[] {
  return items.map((item) => ({
    product_id: item.product_id,
    quantity: parseQuantity(item.quantity),
    unit_price: emptyToMoney(item.unit_price),
    source_protocol_id: item.source_protocol_id,
  }))
}

export function lineTotal(item: SaleItemDraft): string {
  const qty = parseQuantity(item.quantity)
  const price = parseMoneyAmount(item.unit_price)
  if (!Number.isFinite(qty) || !Number.isFinite(price) || qty <= 0) {
    return '0.00'
  }
  return money2(qty * price)
}

export function expectedFromDrafts(items: SaleItemDraft[]): number {
  return items.reduce((sum, item) => sum + Number(lineTotal(item)), 0)
}

export function itemIsBelowMin(item: SaleItemDraft): boolean {
  if (item.min_unit_price === undefined) {
    return Boolean(item.is_below_minimum)
  }
  const price = parseMoneyAmount(item.unit_price)
  const min = Number(item.min_unit_price)
  return Number.isFinite(price) && Number.isFinite(min) && price < min
}

export function paymentsToDrafts(payments: SalePayment[] | undefined): SalePaymentDraft[] {
  return (payments ?? []).map((payment, index) => ({
    key: `pay-${payment.id}-${index}`,
    payment_method_id: String(payment.payment_method_id),
    amount: payment.amount,
    card_operator_id: payment.card_operator_id ? String(payment.card_operator_id) : '',
    card_brand_id: payment.card_brand_id ? String(payment.card_brand_id) : '',
    installments: payment.installments ? String(payment.installments) : '',
  }))
}

export function emptyPaymentDraft(amount = ''): SalePaymentDraft {
  return {
    key: `pay-${Date.now()}-${Math.random().toString(16).slice(2)}`,
    payment_method_id: '',
    amount,
    card_operator_id: '',
    card_brand_id: '',
    installments: '',
  }
}

export function draftsToPaymentPayloads(payments: SalePaymentDraft[]): SalePaymentPayload[] {
  return payments.map((payment) => ({
    payment_method_id: Number(payment.payment_method_id),
    amount: emptyToMoney(payment.amount) ?? '0',
    card_operator_id: payment.card_operator_id ? Number(payment.card_operator_id) : null,
    card_brand_id: payment.card_brand_id ? Number(payment.card_brand_id) : null,
    installments: payment.installments ? Number(payment.installments) : null,
  }))
}

export function paymentsSum(payments: SalePaymentDraft[]): number {
  return payments.reduce((sum, payment) => {
    const amount = parseMoneyAmount(payment.amount)
    return sum + (Number.isFinite(amount) ? amount : 0)
  }, 0)
}

export function remainingBalance(effective: string | number, payments: SalePaymentDraft[]): number {
  const total = typeof effective === 'number' ? effective : Number(effective)
  return Number(money2((Number.isFinite(total) ? total : 0) - paymentsSum(payments)))
}

export function saleEffective(sale: Sale | null | undefined): string {
  return sale?.effective_amount ?? '0.00'
}
