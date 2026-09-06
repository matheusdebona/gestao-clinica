import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { UserPayload } from '@/types/team-user'

export const ROLE_LABELS: Record<string, string> = {
  receptionist: 'Recepção',
  seller: 'Vendas',
  stock: 'Estoque',
  professional: 'Profissional',
}

export function roleLabel(name: string): string {
  return ROLE_LABELS[name] ?? name
}

const baseSchema = z.object({
  name: z.string().trim().min(1, 'Informe o nome.').max(255, 'Nome muito longo.'),
  email: z.string().trim().email('E-mail inválido.').max(255, 'E-mail muito longo.'),
  password: z.string(),
  password_confirmation: z.string(),
  is_active: z.boolean(),
  roles: z.array(z.string()),
})

export function userFormSchema(isEdit: boolean) {
  return toTypedSchema(
    baseSchema.superRefine((values, ctx) => {
      if (!isEdit || values.password.trim().length > 0) {
        if (values.password.length < 10) {
          ctx.addIssue({
            code: z.ZodIssueCode.custom,
            message: 'Mínimo 10 caracteres.',
            path: ['password'],
          })
        }
        if (values.password !== values.password_confirmation) {
          ctx.addIssue({
            code: z.ZodIssueCode.custom,
            message: 'As senhas não coincidem.',
            path: ['password_confirmation'],
          })
        }
      }

      if (values.roles.length < 1) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: 'Selecione ao menos um papel.',
          path: ['roles'],
        })
      }
    }),
  )
}

export type UserFormValues = {
  name: string
  email: string
  password: string
  password_confirmation: string
  is_active: boolean
  roles: string[]
}

export const emptyUserForm = (): UserFormValues => ({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  is_active: true,
  roles: [],
})

export function toUserPayload(values: UserFormValues, isEdit: boolean): UserPayload {
  const payload: UserPayload = {
    name: values.name.trim(),
    email: values.email.trim(),
    is_active: values.is_active,
    roles: [...values.roles],
  }

  if (!isEdit || values.password.trim()) {
    payload.password = values.password
    payload.password_confirmation = values.password_confirmation
  }

  return payload
}
