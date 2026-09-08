export type AuthMode = 'login' | 'register'

export function resolveAuthMode(route: {
  name?: string | symbol | null
  path: string
  query: { mode?: unknown }
}): AuthMode {
  if (route.name === 'register' || route.path === '/register' || route.query.mode === 'register') {
    return 'register'
  }
  return 'login'
}

export function authPathForMode(mode: AuthMode): '/login' | '/register' {
  return mode === 'register' ? '/register' : '/login'
}
