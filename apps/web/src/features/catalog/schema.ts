import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'

export const brandFormSchema = toTypedSchema(
  z.object({
    name: z.string().trim().min(1, 'Informe o nome.').max(255, 'Nome muito longo.'),
    is_active: z.boolean(),
  }),
)

export const productTypeFormSchema = toTypedSchema(
  z.object({
    brand_id: z.string().min(1, 'Selecione a marca.'),
    name: z.string().trim().min(1, 'Informe o nome.').max(255, 'Nome muito longo.'),
    is_active: z.boolean(),
  }),
)

export const unitFormSchema = toTypedSchema(
  z.object({
    name: z.string().trim().min(1, 'Informe o nome.').max(255, 'Nome muito longo.'),
    symbol: z.string().trim().min(1, 'Informe o símbolo.').max(32, 'Símbolo muito longo.'),
    is_active: z.boolean(),
  }),
)
