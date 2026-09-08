export const PAYMENT_CATALOG_PERMISSIONS = [
  'payment_methods.manage',
  'card_brands.manage',
  'card_operators.manage',
] as const

export type PaymentCatalogRouteName = 'payment-methods' | 'card-brands' | 'card-operators'

export function canManagePaymentCatalog(can: (permission: string) => boolean): boolean {
  return PAYMENT_CATALOG_PERMISSIONS.some((permission) => can(permission))
}

export function paymentCatalogHome(can: (permission: string) => boolean): PaymentCatalogRouteName {
  if (can('payment_methods.manage')) {
    return 'payment-methods'
  }
  if (can('card_brands.manage')) {
    return 'card-brands'
  }
  if (can('card_operators.manage')) {
    return 'card-operators'
  }
  return 'payment-methods'
}
