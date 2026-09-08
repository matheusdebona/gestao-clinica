import { describe, expect, it } from 'vitest'
import { AUTH_FAILED_MESSAGE, localizeAuthFailedMessage } from './auth-errors'

describe('localizeAuthFailedMessage', () => {
  it('maps the English Laravel auth.failed copy to Portuguese', () => {
    expect(localizeAuthFailedMessage('These credentials do not match our records.')).toBe(
      AUTH_FAILED_MESSAGE,
    )
  })

  it('maps the auth.failed translation key to Portuguese', () => {
    expect(localizeAuthFailedMessage('auth.failed')).toBe(AUTH_FAILED_MESSAGE)
  })

  it('leaves already-translated copy unchanged', () => {
    expect(localizeAuthFailedMessage(AUTH_FAILED_MESSAGE)).toBe(AUTH_FAILED_MESSAGE)
  })

  it('leaves unrelated messages unchanged', () => {
    expect(localizeAuthFailedMessage('O campo e-mail é obrigatório.')).toBe(
      'O campo e-mail é obrigatório.',
    )
  })
})
