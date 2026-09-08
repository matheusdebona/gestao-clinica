import { describe, expect, it } from 'vitest'
import { SALE_WIZARD_LAST_INDEX, SALE_WIZARD_STEP, SALE_WIZARD_STEPS, salePaymentSummary } from './labels'

describe('SALE_WIZARD_STEPS', () => {
  it('orders payments, review, then a dedicated budget step', () => {
    expect(SALE_WIZARD_STEPS.map((step) => step.id)).toEqual([
      'client',
      'items',
      'values',
      'payments',
      'review',
      'budget',
    ])
    expect(SALE_WIZARD_STEPS.map((step) => step.label)).toEqual([
      'Paciente',
      'Itens',
      'Valores',
      'Pagamentos',
      'Revisar',
      'Orçamento',
    ])
    expect(SALE_WIZARD_STEP.payments).toBe(3)
    expect(SALE_WIZARD_STEP.review).toBe(4)
    expect(SALE_WIZARD_STEP.budget).toBe(5)
    expect(SALE_WIZARD_LAST_INDEX).toBe(SALE_WIZARD_STEP.budget)
  })

  it('includes operator, brand and installments on card payments', () => {
    expect(
      salePaymentSummary({
        payment_method: { name: 'Crédito' },
        card_operator: { name: 'Stone' },
        card_brand: { name: 'Visa' },
        installments: 3,
      }),
    ).toBe('Crédito · Stone · Visa · 3x')
  })
})
