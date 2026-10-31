import { describe, expect, it } from 'vitest'
import {
  firstName,
  renderWhatsAppTemplate,
  whatsappDateParts,
  whatsappDigits,
  whatsappUrl,
} from './whatsapp'

describe('whatsapp', () => {
  it('normalizes phones to 55 + digits', () => {
    expect(whatsappDigits('(11) 98765-4321')).toBe('5511987654321')
    expect(whatsappDigits('5511987654321')).toBe('5511987654321')
    expect(whatsappDigits('123')).toBe('')
  })

  it('renders placeholders and drops an empty professional', () => {
    const template = 'Olá, {paciente}! {clinica}: {data} às {hora} com {profissional}. {usuario}'
    expect(
      renderWhatsAppTemplate(template, {
        paciente: 'Ana',
        clinica: 'Bella',
        data: '05/11',
        hora: '14:00',
        profissional: 'Dra. Paula',
        usuario: 'Rita',
      }),
    ).toBe('Olá, Ana! Bella: 05/11 às 14:00 com Dra. Paula. Rita')
    expect(renderWhatsAppTemplate(template, { paciente: 'Ana', clinica: 'Bella', data: '05/11', hora: '14:00' })).toBe(
      'Olá, Ana! Bella: 05/11 às 14:00.',
    )
  })

  it('builds an encoded wa.me url', () => {
    expect(whatsappUrl('11987654321', 'Oi, tudo bem?')).toBe(
      'https://wa.me/5511987654321?text=Oi%2C%20tudo%20bem%3F',
    )
    expect(whatsappUrl('', 'x')).toBeNull()
  })

  it('formats date parts in São Paulo time', () => {
    expect(whatsappDateParts('2026-11-05T17:30:00Z')).toEqual({ data: '05/11', hora: '14:30' })
    expect(firstName('  Camila Rocha ')).toBe('Camila')
  })
})
