import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { CARD_KINDS } from '@/features/payments/labels'
import { emptyToMoney } from '@/lib/formatters'
import type { CardBrandPayload, PaymentMethod, PaymentMethodPayload } from '@/types/sale'

const KINDS = ['cash', 'pix', 'check', 'credit_card', 'debit_card', 'boleto', 'other'] as const

function emptyToPercent(value: string): string | null {
  const trimmed = value.trim().replace(',', '.')
  if (!trimmed) {
    return null
  }
  return trimmed
}

export const paymentMethodFormSchema = toTypedSchema(
  z.object({
    name: z.string().trim().min(1, 'Informe o nome.').max(255, 'Nome muito longo.'),
    code: z
      .string()
      .trim()
      .min(1, 'Informe o código.')
      .max(50, 'Código muito longo.')
      .regex(/^[A-Za-z0-9_-]+$/, 'Use letras, números, hífen ou underline.'),
    kind: z.string().min(1, 'Selecione o tipo.').refine((value) => KINDS.includes(value as (typeof KINDS)[number]), {
      message: 'Tipo inválido.',
    }),
    fee_percent: z.string().refine((value) => {
      const parsed = emptyToPercent(value)
      if (parsed === null) {
        return true
      }
      const amount = Number(parsed)
      return Number.isFinite(amount) && amount >= 0 && amount <= 100
    }, 'Informe uma taxa entre 0 e 100.'),
    fee_fixed: z.string(),
    is_active: z.boolean(),
  }),
)

export const cardBrandFormSchema = toTypedSchema(
  z.object({
    name: z.string().trim().min(1, 'Informe o nome.').max(255, 'Nome muito longo.'),
    code: z
      .string()
      .trim()
      .min(1, 'Informe o código.')
      .max(50, 'Código muito longo.')
      .regex(/^[A-Za-z0-9_-]+$/, 'Use letras, números, hífen ou underline.'),
    is_active: z.boolean(),
  }),
)

export type PaymentMethodFormValues = {
  name: string
  code: string
  kind: string
  fee_percent: string
  fee_fixed: string
  is_active: boolean
}

export function emptyPaymentMethodForm(): PaymentMethodFormValues {
  return {
    name: '',
    code: '',
    kind: '',
    fee_percent: '',
    fee_fixed: '',
    is_active: true,
  }
}

export function paymentMethodToFormValues(method: PaymentMethod | null | undefined): PaymentMethodFormValues {
  return {
    name: method?.name ?? '',
    code: method?.code ?? '',
    kind: method?.kind ?? '',
    fee_percent: method?.fee_percent ?? '',
    fee_fixed: method?.fee_fixed ?? '',
    is_active: method?.is_active ?? true,
  }
}

export function toPaymentMethodPayload(values: PaymentMethodFormValues): PaymentMethodPayload {
  const isCard = CARD_KINDS.has(values.kind)
  return {
    name: values.name.trim(),
    code: values.code.trim(),
    kind: values.kind,
    fee_percent: isCard ? null : emptyToPercent(values.fee_percent),
    fee_fixed: isCard ? null : emptyToMoney(values.fee_fixed),
    is_active: values.is_active,
  }
}

export function toCardBrandPayload(values: { name: string; code: string; is_active: boolean }): CardBrandPayload {
  return {
    name: values.name.trim(),
    code: values.code.trim(),
    is_active: values.is_active,
  }
}
