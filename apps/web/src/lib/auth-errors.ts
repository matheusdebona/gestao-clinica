export const AUTH_FAILED_MESSAGE = 'E-mail ou senha incorretos.'

const AUTH_FAILED_ALIASES = new Set([
  'These credentials do not match our records.',
  'auth.failed',
])

export function localizeAuthFailedMessage(message: string): string {
  if (AUTH_FAILED_ALIASES.has(message.trim())) {
    return AUTH_FAILED_MESSAGE
  }

  return message
}
