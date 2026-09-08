import { describe, expect, it } from 'vitest'
import { canManagePaymentCatalog, paymentCatalogHome } from './access'

describe('payment catalog access', () => {
  it('allows entry with any catalog permission', () => {
    expect(canManagePaymentCatalog((name) => name === 'card_brands.manage')).toBe(true)
    expect(canManagePaymentCatalog((name) => name === 'card_operators.manage')).toBe(true)
    expect(canManagePaymentCatalog((name) => name === 'payment_methods.manage')).toBe(true)
    expect(canManagePaymentCatalog(() => false)).toBe(false)
  })

  it('routes to the first catalog the user can manage', () => {
    expect(paymentCatalogHome((name) => name === 'card_operators.manage')).toBe('card-operators')
    expect(paymentCatalogHome((name) => name === 'card_brands.manage')).toBe('card-brands')
    expect(paymentCatalogHome((name) => name === 'payment_methods.manage')).toBe('payment-methods')
  })
})
