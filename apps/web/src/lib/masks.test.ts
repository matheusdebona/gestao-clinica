import { describe, expect, it } from 'vitest'
import { emptyToMoney } from '@/lib/formatters'
import { decimalFromMoneyInput, formatMoneyAmount, formatPhoneBR, phoneDigits } from '@/lib/masks'

describe('phoneDigits', () => {
  it('keeps Brazilian mobile and landline digits', () => {
    expect(phoneDigits('(11) 98765-4321')).toBe('11987654321')
    expect(phoneDigits('(11) 3456-7890')).toBe('1134567890')
  })

  it('strips +55 country code when present', () => {
    expect(phoneDigits('+55 11 98765-4321')).toBe('11987654321')
    expect(phoneDigits('551134567890')).toBe('1134567890')
  })

  it('does not strip DDD 55 (Santa Catarina)', () => {
    expect(phoneDigits('55987654321')).toBe('55987654321')
  })
})

describe('formatPhoneBR', () => {
  it('masks landline and mobile as the user types', () => {
    expect(formatPhoneBR('')).toBe('')
    expect(formatPhoneBR('1')).toBe('(1')
    expect(formatPhoneBR('11')).toBe('(11')
    expect(formatPhoneBR('119')).toBe('(11) 9')
    expect(formatPhoneBR('113456')).toBe('(11) 3456')
    expect(formatPhoneBR('1134567890')).toBe('(11) 3456-7890')
    expect(formatPhoneBR('11987654321')).toBe('(11) 98765-4321')
  })
})

describe('decimalFromMoneyInput', () => {
  it('treats typed digits as cents', () => {
    expect(decimalFromMoneyInput('')).toBe('')
    expect(decimalFromMoneyInput('1')).toBe('0.01')
    expect(decimalFromMoneyInput('12')).toBe('0.12')
    expect(decimalFromMoneyInput('123')).toBe('1.23')
    expect(decimalFromMoneyInput('1234')).toBe('12.34')
    expect(decimalFromMoneyInput('123456')).toBe('1234.56')
  })

  it('reads digits out of a formatted BRL string', () => {
    expect(decimalFromMoneyInput('R$ 1.234,56')).toBe('1234.56')
    expect(decimalFromMoneyInput('0,00')).toBe('')
  })
})

describe('formatMoneyAmount', () => {
  it('formats API decimals for the input mask', () => {
    expect(formatMoneyAmount('')).toBe('')
    expect(formatMoneyAmount('80')).toBe('80,00')
    expect(formatMoneyAmount('80.00')).toBe('80,00')
    expect(formatMoneyAmount('1234.56')).toBe('1.234,56')
    expect(formatMoneyAmount('1280,00')).toBe('1.280,00')
  })
})

describe('emptyToMoney', () => {
  it('accepts masked BRL and API decimals', () => {
    expect(emptyToMoney('R$ 1.234,56')).toBe('1234.56')
    expect(emptyToMoney('1.234,56')).toBe('1234.56')
    expect(emptyToMoney('1234.56')).toBe('1234.56')
    expect(emptyToMoney('')).toBeNull()
  })
})
