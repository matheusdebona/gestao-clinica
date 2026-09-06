import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { UserPayload } from '@/types/team-user'

export const ROLE_LABELS: Record<string, string> = {
  receptionist: 'Recepção',
  seller: 'Vendas',
  stock: 'Estoque',
  professional: 'Profissional',
}

export const ROLE_SUMMARIES: Record<string, string> = {
  receptionist: 'Clientes, orçamentos, criar vendas, agenda e ver documentos.',
  seller: 'Clientes, vendas completas, orçamentos e meios de pagamento.',
  stock: 'Produtos, estoque, catálogo (tipos/marcas/unidades) e upload de arquivos.',
  professional: 'Clientes, tratamentos, consumo de sessão, agenda e documentos.',
}

export const PERMISSION_LABELS: Record<string, string> = {
  'users.view': 'Ver equipe',
  'users.create': 'Criar usuários',
  'users.update': 'Editar usuários',
  'users.delete': 'Desativar usuários',
  'roles.manage': 'Gerenciar papéis',
  'permissions.view': 'Ver permissões',
  'files.upload': 'Enviar arquivos',
  'files.delete': 'Excluir arquivos',
  'clinics.view': 'Ver clínica',
  'clinics.manage': 'Gerenciar clínicas',
  'clinics.branding': 'Identidade da clínica',
  'product_types.manage': 'Tipos de produto',
  'brands.manage': 'Marcas',
  'units.manage': 'Unidades de medida',
  'products.view': 'Ver produtos',
  'products.create': 'Criar produtos',
  'products.update': 'Editar produtos',
  'products.delete': 'Excluir produtos',
  'products.adjust_stock': 'Ajustar estoque',
  'protocols.view': 'Ver protocolos',
  'protocols.create': 'Criar protocolos',
  'protocols.update': 'Editar protocolos',
  'protocols.delete': 'Excluir protocolos',
  'clients.view': 'Ver clientes',
  'clients.create': 'Criar clientes',
  'clients.update': 'Editar clientes',
  'clients.delete': 'Desativar clientes',
  'client_origins.manage': 'Origens de cliente',
  'campaigns.manage': 'Campanhas',
  'payment_methods.manage': 'Formas de pagamento',
  'card_operators.manage': 'Operadoras de cartão',
  'card_brands.manage': 'Bandeiras',
  'card_fees.manage': 'Taxas de cartão',
  'sales.view': 'Ver vendas',
  'sales.create': 'Criar vendas',
  'sales.update': 'Editar vendas',
  'sales.confirm': 'Confirmar vendas',
  'sales.cancel': 'Cancelar vendas',
  'budgets.view': 'Ver orçamentos',
  'budgets.create': 'Criar orçamentos',
  'budgets.update': 'Editar orçamentos',
  'budgets.convert': 'Converter orçamentos',
  'documents.view': 'Ver documentos',
  'documents.generate': 'Gerar documentos',
  'documents.delete': 'Excluir documentos',
  'treatments.view': 'Ver tratamentos',
  'treatments.manage': 'Gerenciar tratamentos',
  'treatments.start': 'Abrir tratamentos',
  'treatments.consume': 'Registrar consumo',
  'treatments.complete': 'Concluir tratamentos',
  'treatments.cancel': 'Cancelar tratamentos',
  'appointments.view': 'Ver agenda',
  'appointments.manage': 'Gerenciar agenda',
  'appointments.start': 'Iniciar sessões',
  'appointments.cancel': 'Cancelar sessões',
  'metrics.view': 'Ver métricas',
}

export function roleLabel(name: string): string {
  return ROLE_LABELS[name] ?? name
}

export function roleSummary(name: string): string {
  return ROLE_SUMMARIES[name] ?? ''
}

export function permissionLabel(name: string): string {
  return PERMISSION_LABELS[name] ?? name
}

const baseSchema = z.object({
  name: z.string().trim().min(1, 'Informe o nome.').max(255, 'Nome muito longo.'),
  email: z.string().trim().email('E-mail inválido.').max(255, 'E-mail muito longo.'),
  password: z.string(),
  password_confirmation: z.string(),
  is_active: z.boolean(),
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
    }),
  )
}

export type UserFormValues = {
  name: string
  email: string
  password: string
  password_confirmation: string
  is_active: boolean
}

export type UserFormSubmitValues = UserFormValues & {
  roles: string[]
}

export const emptyUserForm = (): UserFormValues => ({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  is_active: true,
})

export function toUserPayload(values: UserFormSubmitValues, isEdit: boolean): UserPayload {
  const payload: UserPayload = {
    name: values.name.trim(),
    email: values.email.trim(),
    is_active: values.is_active ?? true,
    roles: [...values.roles],
  }

  if (!isEdit || values.password.trim()) {
    payload.password = values.password
    payload.password_confirmation = values.password_confirmation
  }

  return payload
}
