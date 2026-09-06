import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { emptyToInt, emptyToMoney, emptyToNull } from '@/lib/formatters'
import type { Product, ProductPayload } from '@/types/product'

export const productFormSchema = toTypedSchema(
  z.object({
    name: z.string().trim().min(1, 'Informe o nome.').max(255, 'Nome muito longo.'),
    sku: z.string(),
    brand_id: z.string().min(1, 'Selecione a marca.'),
    product_type_id: z.string().min(1, 'Selecione o tipo.'),
    unit_of_measure_id: z.string().min(1, 'Selecione a unidade.'),
    purpose: z.string(),
    cost: z.string(),
    sale_price: z
      .string()
      .trim()
      .min(1, 'Informe o preço de venda.')
      .refine((value) => emptyToMoney(value) !== null && Number(emptyToMoney(value)) >= 0, 'Preço inválido.'),
    min_sale_price: z.string(),
    stock_quantity: z.string(),
    min_stock: z.string(),
    lead_time_days: z.string().refine((value) => {
      if (!value.trim()) {
        return true
      }
      const parsed = Number(value)
      return Number.isInteger(parsed) && parsed >= 0 && parsed <= 365
    }, 'Informe dias entre 0 e 365.'),
    is_active: z.boolean(),
  }),
)

export type ProductFormValues = {
  name: string
  sku: string
  brand_id: string
  product_type_id: string
  unit_of_measure_id: string
  purpose: string
  cost: string
  sale_price: string
  min_sale_price: string
  stock_quantity: string
  min_stock: string
  lead_time_days: string
  is_active: boolean
}

export const emptyProductForm = (): ProductFormValues => ({
  name: '',
  sku: '',
  brand_id: '',
  product_type_id: '',
  unit_of_measure_id: '',
  purpose: '',
  cost: '',
  sale_price: '',
  min_sale_price: '',
  stock_quantity: '',
  min_stock: '',
  lead_time_days: '0',
  is_active: true,
})

export function toProductPayload(values: ProductFormValues, isCreate: boolean): ProductPayload {
  const payload: ProductPayload = {
    name: values.name.trim(),
    sku: emptyToNull(values.sku),
    brand_id: Number(values.brand_id),
    product_type_id: Number(values.product_type_id),
    unit_of_measure_id: Number(values.unit_of_measure_id),
    purpose: emptyToNull(values.purpose),
    sale_price: emptyToMoney(values.sale_price) ?? '0',
    min_sale_price: emptyToMoney(values.min_sale_price),
    min_stock: emptyToMoney(values.min_stock) ?? '0',
    lead_time_days: emptyToInt(values.lead_time_days) ?? 0,
    is_active: values.is_active,
  }

  if (isCreate) {
    payload.cost = emptyToMoney(values.cost) ?? '0'
    payload.stock_quantity = emptyToMoney(values.stock_quantity) ?? '0'
  }

  return payload
}

export function productToFormValues(product: Product): ProductFormValues {
  return {
    name: product.name,
    sku: product.sku ?? '',
    brand_id: String(product.brand_id),
    product_type_id: String(product.product_type_id),
    unit_of_measure_id: String(product.unit_of_measure_id),
    purpose: product.purpose ?? '',
    cost: product.cost ?? '',
    sale_price: product.sale_price ?? '',
    min_sale_price: product.min_sale_price ?? '',
    stock_quantity: product.stock_quantity ?? '',
    min_stock: product.min_stock ?? '',
    lead_time_days: String(product.lead_time_days ?? 0),
    is_active: product.is_active,
  }
}
