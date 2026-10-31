import { describe, expect, it } from 'vitest'
import { isStrongPassword, validateNewPassword } from './password-reset'

describe('isStrongPassword', () => {
  it('matches the registration rules', () => {
    expect(isStrongPassword('NovaSenha#2026')).toBe(true)
    expect(isStrongPassword('curta#1A')).toBe(false)
    expect(isStrongPassword('semsimbolo2026A')).toBe(false)
    expect(isStrongPassword('semnumero#Aaaaa')).toBe(false)
    expect(isStrongPassword('SEMMINUSCULA#2026')).toBe(false)
  })
})

describe('validateNewPassword', () => {
  it('requires the temporary password, a strong new one and a matching confirmation', () => {
    expect(validateNewPassword({ temporary_password: '', password: 'x', password_confirmation: 'y' })).toEqual({
      temporary_password: 'Informe a senha temporária.',
      password: 'Mínimo 10 caracteres, com maiúscula, número e símbolo.',
      password_confirmation: 'As senhas não coincidem.',
    })
  })

  it('rejects reusing the temporary password', () => {
    const errors = validateNewPassword({
      temporary_password: 'Kp7m-X3qr-9tZa',
      password: 'Kp7m-X3qr-9tZa',
      password_confirmation: 'Kp7m-X3qr-9tZa',
    })
    expect(errors.password).toBe('A nova senha deve ser diferente da senha temporária.')
  })

  it('accepts a valid change', () => {
    expect(
      validateNewPassword({
        temporary_password: 'Kp7m-X3qr-9tZa',
        password: 'NovaSenha#2026',
        password_confirmation: 'NovaSenha#2026',
      }),
    ).toEqual({})
  })
})
