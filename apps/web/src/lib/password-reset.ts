export const FORGOT_PASSWORD_SENT_MESSAGE =
  'Se houver uma conta com este e-mail, enviamos uma senha temporária. Confira sua caixa de entrada e o spam.'

export const PASSWORD_RULES_HINT = 'Mínimo 10 caracteres, com maiúscula, número e símbolo.'

export const PASSWORD_CHANGE_REQUIRED_CODE = 'password_change_required'

export const RESET_PASSWORD_PATH = '/reset-password'

/** Mirrors the API rule (Password::defaults()): 10+ chars, upper+lower, number, symbol. */
export function isStrongPassword(value: string): boolean {
  return (
    value.length >= 10 &&
    /[a-z]/.test(value) &&
    /[A-Z]/.test(value) &&
    /\d/.test(value) &&
    /[^A-Za-z0-9]/.test(value)
  )
}

export function validateNewPassword(input: {
  temporary_password: string
  password: string
  password_confirmation: string
}): Record<string, string> {
  const errors: Record<string, string> = {}
  if (!input.temporary_password.trim()) {
    errors.temporary_password = 'Informe a senha temporária.'
  }
  if (!isStrongPassword(input.password)) {
    errors.password = PASSWORD_RULES_HINT
  } else if (input.password === input.temporary_password.trim()) {
    errors.password = 'A nova senha deve ser diferente da senha temporária.'
  }
  if (input.password !== input.password_confirmation) {
    errors.password_confirmation = 'As senhas não coincidem.'
  }
  return errors
}
