import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { emptyToMoney, emptyToNull } from '@/lib/formatters'
import type { Product } from '@/types/product'
import type {
  Protocol,
  ProtocolHeaderPayload,
  ProtocolItemPayload,
  ProtocolSavePayload,
} from '@/types/protocol'

export interface ProtocolItemDraft {
  product_id: number
  quantity: string
  product: Product
}

export type ProtocolFormValues = {
  name: string
  description: string
  suggested_price: string
  min_price: string
  special_price: string
  is_active: boolean
}

export const protocolFormSchema = toTypedSchema(
  z.object({
    name: z.string().trim().min(1, 'Informe o nome.').max(255, 'Nome muito longo.'),
    description: z.string(),
    suggested_price: z.string(),
    min_price: z.string(),
    special_price: z.string(),
    is_active: z.boolean(),
  }),
)

export const emptyProtocolForm = (): ProtocolFormValues => ({
  name: '',
  description: '',
  suggested_price: '',
  min_price: '',
  special_price: '',
  is_active: true,
})

export function protocolToFormValues(protocol: Protocol): ProtocolFormValues {
  return {
    name: protocol.name,
    description: protocol.description ?? '',
    suggested_price: protocol.suggested_price ?? '',
    min_price: protocol.min_price ?? '',
    special_price: protocol.special_price ?? '',
    is_active: protocol.is_active,
  }
}

export function protocolToItemDrafts(protocol: Protocol): ProtocolItemDraft[] {
  return (protocol.items ?? [])
    .filter((item): item is typeof item & { product: Product } => Boolean(item.product))
    .map((item) => ({
      product_id: item.product_id,
      quantity: String(Number(item.quantity)),
      product: item.product,
    }))
}

export function parseQuantity(value: string): number {
  const parsed = Number(emptyToMoney(value) ?? '')
  return Number.isFinite(parsed) ? parsed : NaN
}

export function computedTotals(items: ProtocolItemDraft[]) {
  let totalCost = 0
  let saleTotal = 0
  let minTotal = 0

  for (const item of items) {
    const qty = parseQuantity(item.quantity)
    if (!Number.isFinite(qty) || qty <= 0) {
      continue
    }
    totalCost += Number(item.product.cost) * qty
    saleTotal += Number(item.product.sale_price) * qty
    const floor =
      item.product.min_sale_price !== null && item.product.min_sale_price !== ''
        ? Number(item.product.min_sale_price)
        : Number(item.product.cost)
    minTotal += floor * qty
  }

  return { totalCost, saleTotal, minTotal }
}

export function moneyInput(value: number, digits = 2): string {
  if (!Number.isFinite(value)) {
    return ''
  }
  return value.toFixed(digits)
}

export function lineSale(item: ProtocolItemDraft): string | null {
  const qty = parseQuantity(item.quantity)
  if (!Number.isFinite(qty) || qty <= 0) {
    return null
  }
  return moneyInput(Number(item.product.sale_price) * qty)
}

export function toItemPayloads(items: ProtocolItemDraft[]): ProtocolItemPayload[] {
  return items.map((item) => ({
    product_id: item.product_id,
    quantity: emptyToMoney(item.quantity) ?? item.quantity,
  }))
}

export function toProtocolSavePayload(
  values: ProtocolFormValues,
  items: ProtocolItemDraft[],
  flags: { suggestedDirty: boolean; minDirty: boolean },
): ProtocolSavePayload {
  const header: ProtocolHeaderPayload = {
    name: values.name.trim(),
    description: emptyToNull(values.description),
    is_active: values.is_active,
    special_price: emptyToMoney(values.special_price),
  }

  if (flags.suggestedDirty) {
    header.suggested_price = emptyToMoney(values.suggested_price) ?? '0'
  }
  if (flags.minDirty) {
    header.min_price = emptyToMoney(values.min_price) ?? '0'
  }

  return {
    header,
    items: toItemPayloads(items),
  }
}
