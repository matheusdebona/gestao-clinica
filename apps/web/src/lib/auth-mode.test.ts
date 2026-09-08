import { describe, expect, it } from 'vitest'
import { authPathForMode, resolveAuthMode } from './auth-mode'

describe('resolveAuthMode', () => {
  it('uses /register as signup', () => {
    expect(resolveAuthMode({ name: 'register', path: '/register', query: {} })).toBe('register')
    expect(resolveAuthMode({ path: '/register', query: {} })).toBe('register')
  })

  it('uses /login as login', () => {
    expect(resolveAuthMode({ name: 'login', path: '/login', query: {} })).toBe('login')
    expect(resolveAuthMode({ path: '/login', query: {} })).toBe('login')
  })

  it('accepts ?mode=register on /login as fallback', () => {
    expect(resolveAuthMode({ name: 'login', path: '/login', query: { mode: 'register' } })).toBe(
      'register',
    )
  })

  it('ignores other query values', () => {
    expect(resolveAuthMode({ path: '/login', query: { mode: 'signup' } })).toBe('login')
  })
})

describe('authPathForMode', () => {
  it('maps mode to the canonical guest path', () => {
    expect(authPathForMode('register')).toBe('/register')
    expect(authPathForMode('login')).toBe('/login')
  })
})
