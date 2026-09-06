import { emptyToMoney } from '@/lib/formatters'
import { parseMoneyAmount, parseQuantity, qtyInput } from '@/features/sales/schema'
import type {
  Appointment,
  AppointmentConsumption,
  ConsumptionPayload,
} from '@/types/appointment'
import type { Product } from '@/types/product'
import type { TreatmentFulfillmentItem } from '@/types/treatment'

export interface ConsumptionDraft {
  key: string
  source: 'suggested' | 'extra'
  product_id: number
  product_name: string
  sale_item_id: number | null
  quantity: string
  remaining_quantity?: string
  stock_quantity?: string
  is_complimentary: boolean
  charged_amount: string
  payment_method_id: string
  card_operator_id: string
  card_brand_id: string
  installments: string
}

function emptyPaymentFields() {
  return {
    payment_method_id: '',
    card_operator_id: '',
    card_brand_id: '',
    installments: '',
  }
}

export function extraKind(line: ConsumptionDraft): 'courtesy' | 'charged' {
  return line.source === 'extra' && !line.is_complimentary ? 'charged' : 'courtesy'
}

export function setExtraKind(line: ConsumptionDraft, kind: 'courtesy' | 'charged', salePrice?: string) {
  if (kind === 'courtesy') {
    line.is_complimentary = true
    line.charged_amount = ''
    Object.assign(line, emptyPaymentFields())
    return
  }
  line.is_complimentary = false
  if (!line.charged_amount.trim() && salePrice) {
    line.charged_amount = salePrice
  }
}

export function newExtraDraft(product: Product): ConsumptionDraft {
  return {
    key: `extra-${product.id}-${Date.now()}-${Math.random().toString(16).slice(2)}`,
    source: 'extra',
    product_id: product.id,
    product_name: product.name,
    sale_item_id: null,
    quantity: '1',
    stock_quantity: product.stock_quantity,
    is_complimentary: true,
    charged_amount: '',
    ...emptyPaymentFields(),
  }
}

export function buildConsumptionDrafts(
  fulfillmentItems: TreatmentFulfillmentItem[],
  existing: AppointmentConsumption[] | undefined,
): ConsumptionDraft[] {
  const savedSuggested = new Map<number, AppointmentConsumption>()
  for (const row of existing ?? []) {
    if (row.source === 'suggested' && row.sale_item_id) {
      savedSuggested.set(row.sale_item_id, row)
    }
  }

  const suggested: ConsumptionDraft[] = fulfillmentItems
    .filter((item) => Number(item.remaining_quantity) > 0)
    .map((item) => {
      const saved = savedSuggested.get(item.sale_item_id)
      return {
        key: `suggested-${item.sale_item_id}`,
        source: 'suggested',
        product_id: item.product_id,
        product_name: item.product_name,
        sale_item_id: item.sale_item_id,
        quantity: qtyInput(saved?.quantity ?? item.remaining_quantity),
        remaining_quantity: item.remaining_quantity,
        stock_quantity: item.stock_quantity,
        is_complimentary: false,
        charged_amount: '',
        ...emptyPaymentFields(),
      }
    })

  const extras: ConsumptionDraft[] = (existing ?? [])
    .filter((row) => row.source === 'extra')
    .map((row, index) => ({
      key: `extra-${row.id}-${index}`,
      source: 'extra',
      product_id: row.product_id,
      product_name: row.product?.name ?? `Produto #${row.product_id}`,
      sale_item_id: null,
      quantity: qtyInput(row.quantity),
      stock_quantity: row.product?.stock_quantity,
      is_complimentary: row.is_complimentary,
      charged_amount: row.is_complimentary ? '' : row.charged_amount,
      payment_method_id: row.sale_payment?.payment_method_id
        ? String(row.sale_payment.payment_method_id)
        : '',
      card_operator_id: row.sale_payment?.card_operator_id
        ? String(row.sale_payment.card_operator_id)
        : '',
      card_brand_id: row.sale_payment?.card_brand_id ? String(row.sale_payment.card_brand_id) : '',
      installments: row.sale_payment?.installments ? String(row.sale_payment.installments) : '',
    }))

  return [...suggested, ...extras]
}

export function draftsHaveProducts(drafts: ConsumptionDraft[]): boolean {
  return drafts.some((line) => parseQuantity(line.quantity) > 0)
}

export function validateConsumptionDrafts(drafts: ConsumptionDraft[]): string | null {
  for (const line of drafts) {
    const qty = parseQuantity(line.quantity)
    if (!Number.isFinite(qty) || qty < 0) {
      return `Quantidade inválida em ${line.product_name}.`
    }
    if (qty === 0) {
      continue
    }
    if (line.source === 'extra' && !line.is_complimentary) {
      const amount = parseMoneyAmount(line.charged_amount)
      if (!Number.isFinite(amount) || amount <= 0) {
        return `Informe o valor cobrado de ${line.product_name}.`
      }
      if (!line.payment_method_id) {
        return `Selecione o pagamento de ${line.product_name}.`
      }
    }
  }
  return null
}

export function toConsumptionPayloads(drafts: ConsumptionDraft[]): ConsumptionPayload[] {
  return drafts
    .filter((line) => parseQuantity(line.quantity) > 0)
    .map((line) => {
      const payload: ConsumptionPayload = {
        source: line.source,
        product_id: line.product_id,
        quantity: parseQuantity(line.quantity),
      }
      if (line.source === 'suggested' && line.sale_item_id) {
        payload.sale_item_id = line.sale_item_id
      }
      if (line.source === 'extra') {
        payload.is_complimentary = line.is_complimentary
        if (!line.is_complimentary) {
          payload.charged_amount = emptyToMoney(line.charged_amount) ?? line.charged_amount
          payload.payment = {
            payment_method_id: Number(line.payment_method_id),
            card_operator_id: line.card_operator_id ? Number(line.card_operator_id) : null,
            card_brand_id: line.card_brand_id ? Number(line.card_brand_id) : null,
            installments: line.installments ? Number(line.installments) : null,
          }
        }
      }
      return payload
    })
}

export function stockWarningsFromAppointment(appointment: Appointment | undefined) {
  const raw = appointment?.stock_warnings ?? appointment?.stock_warning
  if (!Array.isArray(raw)) {
    return []
  }
  return raw.filter(
    (item): item is { product_id: number; product_name: string; suggested_quantity: string; stock_quantity: string } =>
      typeof item === 'object' && item !== null && 'product_name' in item,
  )
}
