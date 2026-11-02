import { api } from '@/lib/api'

/** Conexão ativa do Claude (um usuário × um cliente OAuth). Nunca traz o token. */
export interface IntegrationConnection {
  id: string
  user_id: number
  user_name: string
  client_id: string
  client_name: string
  connected_at: string | null
  expires_at: string | null
  last_used_at: string | null
  calls: number
}

export interface IntegrationRecentCall {
  id: number
  tool: string
  user_name: string | null
  status: 'ok' | 'error'
  created_at: string | null
}

export interface Integration {
  key: 'claude_mcp' | string
  name: string
  read_only: boolean
  status: 'connected' | 'not_connected'
  server_url: string
  connections: IntegrationConnection[]
  recent_calls: IntegrationRecentCall[]
}

export const INTEGRATIONS_KEY = ['integrations'] as const

export async function listIntegrations(): Promise<Integration[]> {
  const payload = await api<{ data: Integration[] }>('/integrations')
  return payload.data
}

export async function revokeClaudeConnection(userId: number, clientId: string): Promise<void> {
  await api(`/integrations/claude/connections/${userId}/${encodeURIComponent(clientId)}`, {
    method: 'DELETE',
  })
}

/** Nome amigável das ferramentas do conector (para a lista de chamadas recentes). */
export const MCP_TOOL_LABELS: Record<string, string> = {
  resumo_faturamento: 'Resumo de faturamento',
  vendas_por_produto: 'Vendas por produto',
  dre_do_mes: 'DRE do mês',
  agenda: 'Agenda',
  leads: 'Leads',
  buscar_pacientes: 'Buscar pacientes',
  paciente_detalhe: 'Ficha do paciente',
  metas_do_mes: 'Metas e remuneração',
  estoque_baixo: 'Estoque baixo',
  retornos_pendentes: 'Retornos pendentes',
  orcamentos: 'Orçamentos',
}

export function toolLabel(tool: string): string {
  return MCP_TOOL_LABELS[tool] ?? tool
}
