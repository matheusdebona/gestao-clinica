import { describe, expect, it } from 'vitest'
import { leadFormZod, leadToForm, toLeadPayload } from '@/features/leads/schema'
import type { Lead } from '@/types/lead'

const base = { name: 'Ana', phone: '', client_origin_id: '1', lead_status_id: '', notes: '' }

describe('lead form: telefone opcional', () => {
  it('aceita lead sem telefone e envia null', () => {
    expect(leadFormZod.safeParse(base).success).toBe(true)
    expect(toLeadPayload(base).phone).toBeNull()
    expect(toLeadPayload({ ...base, phone: '(  )     -    ' }).phone).toBeNull()
  })

  it('valida o telefone quando informado', () => {
    const short = leadFormZod.safeParse({ ...base, phone: '(11) 9888' })
    expect(short.success).toBe(false)
    expect(short.error?.issues[0]?.message).toBe('Informe DDD e número.')
    expect(leadFormZod.safeParse({ ...base, phone: '(11) 98888-7777' }).success).toBe(true)
    expect(toLeadPayload({ ...base, phone: '(11) 98888-7777' }).phone).toBe('11988887777')
  })

  it('edita lead sem telefone com campo vazio', () => {
    const lead = { name: 'Ana', phone: null, client_origin_id: 1, lead_status_id: 2, notes: null } as unknown as Lead
    expect(leadToForm(lead).phone).toBe('')
  })
})
