import { describe, expect, it } from 'vitest'
import {
  SHELL_NAV,
  SHELL_NAV_OPEN_STORAGE_KEY,
  buildVisibleNav,
  flattenNavItems,
  groupContainingPath,
  isNavActive,
  persistOpenGroups,
  readStoredOpenGroups,
  splitMobileNav,
  toggleGroupOpen,
  withActiveGroupOpen,
  type VisibleNav,
} from './shell-nav'

const ADMIN = [
  'integrations.manage',
  'users.view',
  'clinics.branding',
  'products.view',
  'protocols.view',
  'clients.view',
  'client_origins.manage',
  'campaigns.manage',
  'payment_methods.manage',
  'card_operators.manage',
  'card_brands.manage',
  'sales.view',
  'budgets.view',
  'treatments.view',
  'appointments.view',
  'metrics.view',
  'leads.view',
  'leads.manage',
  'lead_statuses.manage',
  'finance.view',
]

const RECEPTIONIST = [
  'clients.view',
  'budgets.view',
  'sales.view',
  'sales.create',
  'documents.view',
  'appointments.view',
  'appointments.manage',
  'appointments.start',
  'appointments.cancel',
  'leads.view',
  'leads.manage',
]

const SELLER = [
  'clients.view',
  'products.view',
  'protocols.view',
  'sales.view',
  'sales.create',
  'sales.update',
  'sales.confirm',
  'sales.cancel',
  'budgets.view',
  'treatments.view',
  'treatments.start',
  'payment_methods.manage',
  'card_operators.manage',
  'card_brands.manage',
  'leads.view',
  'leads.manage',
]

const STOCK = [
  'products.view',
  'products.create',
  'products.update',
  'products.delete',
  'products.adjust_stock',
  'product_types.manage',
  'brands.manage',
  'units.manage',
  'files.upload',
]

const PROFESSIONAL = [
  'clients.view',
  'treatments.view',
  'treatments.manage',
  'treatments.start',
  'treatments.consume',
  'treatments.complete',
  'treatments.cancel',
  'appointments.view',
  'appointments.manage',
  'appointments.start',
  'appointments.cancel',
  'documents.view',
  'documents.generate',
  'documents.delete',
  'products.view',
]

function labels(nav: VisibleNav[]): string[] {
  return nav.map((entry) => {
    if (entry.kind === 'item') {
      return entry.label
    }
    return `${entry.label}[${entry.items.map((item) => item.label).join(', ')}]`
  })
}

function allItemIds(nav: VisibleNav[]): string[] {
  return flattenNavItems(nav).map((item) => item.id)
}

describe('buildVisibleNav', () => {
  it('keeps every catalog destination when permissions are empty', () => {
    const nav = buildVisibleNav([], 'payment-methods')
    expect(allItemIds(nav)).toEqual([
      'home',
      'leads',
      'agenda',
      'patients',
      'sales',
      'budgets',
      'returns',
      'treatments',
      'protocols',
      'products',
      'alerts',
      'metrics',
      'finance',
      'team',
      'payments',
      'settings',
      'integrations',
    ])
  })

  it('groups admin items and keeps every previous destination', () => {
    const nav = buildVisibleNav(ADMIN, 'payment-methods')
    expect(labels(nav)).toEqual([
      'Início',
      'Atendimento[Agenda, Pacientes, Retornos, Tratamentos]',
      'Comercial[Leads, Orçamentos, Vendas, Protocolos]',
      'Clínica[Produtos, Alertas]',
      'Financeiro[Métricas, DRE, Pagamentos]',
      'Gestão[Equipe, Configurações, Integrações]',
    ])
  })

  it('hides empty groups and flattens a single visible child for recepção', () => {
    const nav = buildVisibleNav(RECEPTIONIST, 'payment-methods')
    expect(labels(nav)).toEqual([
      'Início',
      'Atendimento[Agenda, Pacientes, Retornos]',
      'Comercial[Leads, Orçamentos, Vendas]',
      'Alertas',
    ])
    expect(nav.some((entry) => entry.kind === 'group' && entry.id === 'clinica')).toBe(false)
    expect(nav.some((entry) => entry.kind === 'group' && entry.id === 'financeiro')).toBe(false)
    expect(nav.some((entry) => entry.kind === 'group' && entry.id === 'gestao')).toBe(false)
  })

  it('flattens Pagamentos for vendedor when Financeiro has one child', () => {
    const nav = buildVisibleNav(SELLER, 'card-brands')
    expect(labels(nav)).toEqual([
      'Início',
      'Atendimento[Pacientes, Retornos, Tratamentos]',
      'Comercial[Leads, Orçamentos, Vendas, Protocolos]',
      'Clínica[Produtos, Alertas]',
      'Pagamentos',
    ])
    const payments = nav.find((entry) => entry.kind === 'item' && entry.id === 'payments')
    expect(payments?.kind === 'item' && payments.to).toBe('/card-brands')
  })

  it('shows only clínica items for estoque', () => {
    const nav = buildVisibleNav(STOCK, 'payment-methods')
    expect(labels(nav)).toEqual(['Início', 'Clínica[Produtos, Alertas]'])
  })

  it('shows atendimento and clínica for profissional', () => {
    const nav = buildVisibleNav(PROFESSIONAL, 'payment-methods')
    expect(labels(nav)).toEqual([
      'Início',
      'Atendimento[Agenda, Pacientes, Tratamentos]',
      'Clínica[Produtos, Alertas]',
    ])
  })
})

describe('splitMobileNav', () => {
  it('pins the original first destinations so the tab bar stays stable', () => {
    const { pins, more, needsMore } = splitMobileNav(buildVisibleNav(ADMIN, 'payment-methods'))
    expect(needsMore).toBe(true)
    expect(pins.map((item) => item.label)).toEqual(['Início', 'Leads', 'Agenda', 'Pacientes'])
    expect(labels(more)).toEqual([
      'Atendimento[Retornos, Tratamentos]',
      'Comercial[Orçamentos, Vendas, Protocolos]',
      'Clínica[Produtos, Alertas]',
      'Financeiro[Métricas, DRE, Pagamentos]',
      'Gestão[Equipe, Configurações, Integrações]',
    ])
  })

  it('does not show Mais when everything fits the tab bar', () => {
    const { pins, more, needsMore } = splitMobileNav(buildVisibleNav(STOCK, 'payment-methods'))
    expect(needsMore).toBe(false)
    expect(pins.map((item) => item.label)).toEqual(['Início', 'Produtos', 'Alertas'])
    expect(more).toEqual([])
  })
})

describe('isNavActive', () => {
  it('treats the home path as exact', () => {
    expect(isNavActive('/', '/')).toBe(true)
    expect(isNavActive('/leads', '/')).toBe(false)
  })

  it('matches aliases and nested paths', () => {
    expect(isNavActive('/card-brands/new', '/payment-methods', ['/payment-methods', '/card-brands'])).toBe(
      true,
    )
    expect(isNavActive('/settings/impostos', '/settings')).toBe(true)
    expect(isNavActive('/financeiro/despesas', '/financeiro')).toBe(true)
  })
})

describe('open group state', () => {
  it('opens the group that owns the current path', () => {
    const nav = buildVisibleNav(ADMIN, 'payment-methods')
    expect(groupContainingPath(nav, '/clients/12')).toBe('atendimento')
    expect(groupContainingPath(nav, '/')).toBeNull()
    expect(withActiveGroupOpen({ comercial: true }, 'atendimento')).toEqual({
      comercial: true,
      atendimento: true,
    })
  })

  it('remembers toggles in storage', () => {
    const memory = new Map<string, string>()
    const storage = {
      getItem: (key: string) => memory.get(key) ?? null,
      setItem: (key: string, value: string) => {
        memory.set(key, value)
      },
    }
    persistOpenGroups(toggleGroupOpen({}, 'comercial'), storage)
    expect(memory.get(SHELL_NAV_OPEN_STORAGE_KEY)).toBe('{"comercial":true}')
    expect(readStoredOpenGroups(storage)).toEqual({ comercial: true })
  })

  it('ignores invalid stored JSON', () => {
    expect(
      readStoredOpenGroups({
        getItem: () => 'nope',
      }),
    ).toEqual({})
  })
})

describe('catalog coverage', () => {
  it('declares the same destinations the shell used to list', () => {
    const items = flattenNavItems(buildVisibleNav([], 'payment-methods'))
    expect(items.map((item) => item.to).sort()).toEqual(
      [
        '/',
        '/appointments',
        '/budgets',
        '/clients',
        '/financeiro',
        '/integracoes',
        '/leads',
        '/metrics',
        '/notifications',
        '/payment-methods',
        '/products',
        '/protocols',
        '/retornos',
        '/sales',
        '/settings',
        '/treatments',
        '/users',
      ].sort(),
    )
    expect(SHELL_NAV.some((node) => node.kind === 'group' && node.group.id === 'gestao')).toBe(true)
  })
})
