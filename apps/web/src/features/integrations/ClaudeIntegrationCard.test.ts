// @vitest-environment happy-dom
import { mount } from '@vue/test-utils'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { createPinia, setActivePinia } from 'pinia'
import { describe, expect, it, vi } from 'vitest'
import { routes } from '@/app/router'
import ClaudeIntegrationCard from './ClaudeIntegrationCard.vue'
import { MCP_TOOL_LABELS, toolLabel, type Integration } from './api'

vi.mock('./api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('./api')>()),
  revokeClaudeConnection: vi.fn().mockResolvedValue(undefined),
}))

const base: Integration = {
  key: 'claude_mcp',
  name: 'Claude',
  read_only: true,
  status: 'not_connected',
  server_url: 'https://api.hofpay.com.br/mcp',
  connections: [],
  recent_calls: [],
}

function mountCard(integration: Integration) {
  const pinia = createPinia()
  setActivePinia(pinia)
  return mount(ClaudeIntegrationCard, {
    props: { integration },
    global: { plugins: [pinia, [VueQueryPlugin, { queryClient: new QueryClient() }]] },
    attachTo: document.body,
  })
}

describe('ClaudeIntegrationCard', () => {
  it('shows not connected, the server URL and a collapsed guide', async () => {
    const w = mountCard(base)
    expect(w.get('[data-testid="claude-status"]').text()).toBe('Não conectado')
    expect((w.get('[data-testid="claude-server-url"]').element as HTMLInputElement).value).toBe(
      'https://api.hofpay.com.br/mcp',
    )
    expect(w.text()).toContain('Só leitura')
    expect(w.find('[data-testid="claude-no-connections"]').exists()).toBe(true)
    const guide = w.get('[data-testid="claude-guide"]')
    expect((guide.element as HTMLElement).style.display).toBe('none')
    await w.get('button[aria-controls="claude-guide"]').trigger('click')
    expect((guide.element as HTMLElement).style.display).toBe('')
    expect(guide.text()).toContain('Adicionar conector personalizado')
    expect(w.text()).not.toMatch(/cliente|avaliação|médic/i)
  })

  it('lists connections with Revogar as the negative (left) action', () => {
    const w = mountCard({
      ...base,
      status: 'connected',
      connections: [
        {
          id: '1:abc',
          user_id: 1,
          user_name: 'Ana Admin',
          client_id: 'abc',
          client_name: 'Claude',
          connected_at: '2026-11-01T15:00:00Z',
          expires_at: null,
          last_used_at: null,
          calls: 3,
        },
      ],
      recent_calls: [
        { id: 9, tool: 'dre_do_mes', user_name: 'Ana Admin', status: 'ok', created_at: '2026-11-01T15:05:00Z' },
      ],
    })
    expect(w.get('[data-testid="claude-status"]').text()).toBe('Conectado')
    const row = w.get('[data-testid="claude-connections"] li')
    expect(row.text()).toContain('Ana Admin · Claude')
    expect(row.text()).toContain('3 consultas')
    const revoke = w.get('[data-testid="claude-revoke"]')
    expect(revoke.text()).toBe('Revogar')
    // ActionBar #negative group = left on desktop.
    expect(revoke.element.closest('div')!.parentElement!.firstElementChild!.contains(revoke.element)).toBe(true)
    expect(w.get('[data-testid="claude-recent-calls"]').text()).toContain('DRE do mês')
  })

  it('has a label for every MCP tool', () => {
    expect(Object.keys(MCP_TOOL_LABELS)).toHaveLength(11)
    expect(toolLabel('desconhecida')).toBe('desconhecida')
  })

  it('protects the route with integrations.manage', () => {
    const shell = routes.find((route) => route.path === '/')
    const route = shell?.children?.find((r) => r.name === 'integrations')
    expect(route?.path).toBe('integracoes')
    expect(route?.meta).toMatchObject({ permission: 'integrations.manage', title: 'Integrações' })
  })
})
