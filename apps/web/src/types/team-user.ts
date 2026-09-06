export interface ClinicUser {
  id: number
  name: string
  email: string
  is_active: boolean
  clinic_id: number | null
  roles: string[]
  permissions?: string[]
  created_at?: string
  updated_at?: string
}

export interface AssignableRole {
  name: string
  permissions: string[]
}

export interface UserPayload {
  name: string
  email: string
  password?: string
  password_confirmation?: string
  is_active?: boolean
  roles: string[]
}
