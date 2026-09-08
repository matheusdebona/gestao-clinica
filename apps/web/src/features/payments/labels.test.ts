import { describe, expect, it } from 'vitest'
import { formatFeePercent, slugifyCode } from './labels'

describe('slugifyCode', () => {
  it('strips accents and punctuation', () => {
    expect(slugifyCode('Cartão de crédito')).toBe('cartao_de_credito')
    expect(slugifyCode('PIX')).toBe('pix')
  })
})

describe('formatFeePercent', () => {
  it('formats empty as dash', () => {
    expect(formatFeePercent(null)).toBe('—')
    expect(formatFeePercent('2.5')).toBe('2,5%')
  })
})
