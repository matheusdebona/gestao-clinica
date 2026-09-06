export interface Clinic {
  id: number
  name: string
  email?: string | null
}

export interface AuthUser {
  id: number
  name: string
  email: string
  is_active: boolean
  clinic_id: number | null
  clinic?: Clinic | null
  roles?: string[]
  permissions?: string[]
}

export interface AuthResponse {
  token: string
  token_type: string
  user: AuthUser
}

export interface ApiFieldErrors {
  [field: string]: string[]
}

export class ApiError extends Error {
  status: number
  errors: ApiFieldErrors

  constructor(message: string, status: number, errors: ApiFieldErrors = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }

  first(field: string): string {
    return this.errors[field]?.[0] ?? ''
  }
}
