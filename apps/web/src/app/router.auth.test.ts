// @vitest-environment happy-dom
import { describe, expect, it } from 'vitest'
import { routes } from './router'

describe('auth guest routes', () => {
  it('defines /register as a guest AuthPage', () => {
    const register = routes.find((route) => route.path === '/register')
    expect(register?.name).toBe('register')
    expect(register?.meta).toMatchObject({ guest: true, title: 'Cadastrar' })
  })

  it('keeps /login as a guest AuthPage', () => {
    const login = routes.find((route) => route.path === '/login')
    expect(login?.name).toBe('login')
    expect(login?.meta).toMatchObject({ guest: true, title: 'Entrar' })
  })
})

describe('payment catalog routes', () => {
  it('registers card operator CRUD under the clinic shell', () => {
    const shell = routes.find((route) => route.path === '/')
    const children = shell?.children ?? []
    const names = children.map((route) => route.name)
    expect(names).toEqual(
      expect.arrayContaining([
        'card-operators',
        'card-operators-new',
        'card-operators-edit',
        'card-operators-show',
      ]),
    )
  })
})
