import { describe, expect, it } from 'vitest'
import {
  isNotificationUnread,
  notificationHref,
  notificationMessage,
  notificationTitle,
  notificationTypeKey,
} from '@/features/notifications/links'
import type { ClinicNotification } from '@/types/notification'

function item(overrides: Partial<ClinicNotification> = {}): ClinicNotification {
  return {
    id: 'n-1',
    type: 'App\\Notifications\\LowStockProductNotification',
    type_key: 'low_stock',
    data: {
      type: 'low_stock',
      title: 'Estoque baixo',
      message: 'O produto Ácido está no mínimo.',
      product_id: 12,
    },
    read_at: null,
    created_at: '2026-09-06T10:00:00.000000Z',
    updated_at: '2026-09-06T10:00:00.000000Z',
    ...overrides,
  }
}

describe('notification inbox mapping', () => {
  it('deep-links stock alerts to the product and agenda alerts to the appointment', () => {
    expect(notificationHref(item())).toBe('/products/12')
    expect(
      notificationHref(
        item({
          type_key: 'projected_low_stock',
          data: { type: 'projected_low_stock', product_id: 8 },
        }),
      ),
    ).toBe('/products/8')
    expect(
      notificationHref(
        item({
          type_key: 'appointment_stock_warning',
          data: { type: 'appointment_stock_warning', appointment_id: 44 },
        }),
      ),
    ).toBe('/appointments/44')
  })

  it('uses data.type when type_key is missing and ignores Laravel FQCN type', () => {
    const unknownFqcn = item({
      type: 'App\\Notifications\\LowStockProductNotification',
      type_key: null,
      data: { type: 'projected_low_stock', product_id: 3 },
    })
    expect(notificationTypeKey(unknownFqcn)).toBe('projected_low_stock')
    expect(notificationHref(unknownFqcn)).toBe('/products/3')
  })

  it('keeps unknown types in the list without a destination', () => {
    const mystery = item({
      type: 'App\\Notifications\\FutureNotification',
      type_key: 'future_channel',
      data: { type: 'future_channel', title: 'Canal novo', message: 'Sem rota.' },
    })
    expect(notificationHref(mystery)).toBeNull()
    expect(notificationTitle(mystery)).toBe('Canal novo')
    expect(notificationMessage(mystery)).toBe('Sem rota.')
    expect(isNotificationUnread(mystery)).toBe(true)
  })

  it('falls back to a generic title when payload fields are missing', () => {
    const empty = item({
      type_key: 'totally_new',
      data: { type: 'totally_new' },
    })
    expect(notificationTitle(empty)).toBe('Alerta')
    expect(notificationMessage(empty)).toBe('')
    expect(notificationHref(empty)).toBeNull()
  })

  it('accepts numeric ids encoded as strings and skips known types without an id', () => {
    expect(
      notificationHref(
        item({
          type_key: 'low_stock',
          data: { type: 'low_stock', product_id: '12' },
        }),
      ),
    ).toBe('/products/12')
    expect(
      notificationHref(item({ data: { type: 'low_stock', title: 'Estoque baixo' } })),
    ).toBeNull()
  })
})
