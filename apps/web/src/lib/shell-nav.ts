import type { Component } from 'vue'
import {
  Bell,
  Building2,
  CalendarDays,
  ChartNoAxesCombined,
  ClipboardList,
  CreditCard,
  FileText,
  Handshake,
  HeartPulse,
  Landmark,
  LayoutDashboard,
  Megaphone,
  Package,
  Plug,
  Receipt,
  RotateCcw,
  Settings,
  SlidersHorizontal,
  Stethoscope,
  UserCog,
  Users,
  Wallet,
} from '@lucide/vue'

/** Alertas: estoque (products.view) e resumo de leads parados (leads.manage). */
export const SHELL_ALERT_PERMISSIONS = ['products.view', 'leads.manage'] as const

export const SHELL_SETTINGS_PERMISSIONS = [
  'lead_statuses.manage',
  'client_origins.manage',
  'campaigns.manage',
  'clinics.branding',
  'finance.view',
] as const

export const SHELL_PAYMENT_PERMISSIONS = [
  'payment_methods.manage',
  'card_brands.manage',
  'card_operators.manage',
] as const

/** Formato antigo (vários grupos abertos); migrado e apagado na primeira leitura. */
export const SHELL_NAV_OPEN_STORAGE_KEY = 'gc_shell_nav_open'

/** Grupo aberto do menu (um só); string vazia = todos fechados. */
export const SHELL_NAV_OPEN_GROUP_STORAGE_KEY = 'gc_shell_nav_open_group'

/** Max items in the mobile tab bar, including "Mais". */
export const MOBILE_NAV_SLOTS = 5

export type ShellNavItemDef = {
  id: string
  to: string
  label: string
  icon: Component
  permission?: string
  anyPermission?: string[]
  aliases?: string[]
  /** Original flat order — keeps the mobile tab bar pins stable. */
  order: number
}

export type ShellNavGroupDef = {
  id: string
  label: string
  icon: Component
  items: ShellNavItemDef[]
}

export type ShellNavNodeDef =
  | { kind: 'item'; item: ShellNavItemDef }
  | { kind: 'group'; group: ShellNavGroupDef }

export type VisibleNavItem = ShellNavItemDef & { kind: 'item' }

export type VisibleNavGroup = {
  kind: 'group'
  id: string
  label: string
  icon: Component
  items: ShellNavItemDef[]
}

export type VisibleNav = VisibleNavItem | VisibleNavGroup

export type ShellNavContext = {
  permissions: string[]
  paymentHome: string
}

export const SHELL_NAV: ShellNavNodeDef[] = [
  {
    kind: 'item',
    item: { id: 'home', to: '/', label: 'Início', icon: LayoutDashboard, order: 0 },
  },
  {
    kind: 'group',
    group: {
      id: 'atendimento',
      label: 'Atendimento',
      icon: HeartPulse,
      items: [
        {
          id: 'agenda',
          to: '/appointments',
          label: 'Agenda',
          icon: CalendarDays,
          permission: 'appointments.view',
          order: 2,
        },
        {
          id: 'patients',
          to: '/clients',
          label: 'Pacientes',
          icon: Users,
          permission: 'clients.view',
          order: 3,
        },
        {
          id: 'returns',
          to: '/retornos',
          label: 'Retornos',
          icon: RotateCcw,
          permission: 'sales.view',
          order: 6,
        },
        {
          id: 'treatments',
          to: '/treatments',
          label: 'Tratamentos',
          icon: Stethoscope,
          permission: 'treatments.view',
          order: 7,
        },
      ],
    },
  },
  {
    kind: 'group',
    group: {
      id: 'comercial',
      label: 'Comercial',
      icon: Handshake,
      items: [
        {
          id: 'leads',
          to: '/leads',
          label: 'Leads',
          icon: Megaphone,
          permission: 'leads.view',
          order: 1,
        },
        {
          id: 'budgets',
          to: '/budgets',
          label: 'Orçamentos',
          icon: FileText,
          permission: 'budgets.view',
          order: 5,
        },
        {
          id: 'sales',
          to: '/sales',
          label: 'Vendas',
          icon: Receipt,
          permission: 'sales.view',
          order: 4,
        },
        {
          id: 'protocols',
          to: '/protocols',
          label: 'Protocolos',
          icon: ClipboardList,
          permission: 'protocols.view',
          order: 8,
        },
      ],
    },
  },
  {
    kind: 'group',
    group: {
      id: 'clinica',
      label: 'Clínica',
      icon: Building2,
      items: [
        {
          id: 'products',
          to: '/products',
          label: 'Produtos',
          icon: Package,
          permission: 'products.view',
          order: 9,
        },
        {
          id: 'alerts',
          to: '/notifications',
          label: 'Alertas',
          icon: Bell,
          anyPermission: [...SHELL_ALERT_PERMISSIONS],
          order: 10,
        },
      ],
    },
  },
  {
    kind: 'group',
    group: {
      id: 'financeiro',
      label: 'Financeiro',
      icon: Landmark,
      items: [
        {
          id: 'metrics',
          to: '/metrics',
          label: 'Métricas',
          icon: ChartNoAxesCombined,
          permission: 'metrics.view',
          order: 11,
        },
        {
          id: 'finance',
          to: '/financeiro',
          label: 'DRE',
          icon: Wallet,
          permission: 'finance.view',
          order: 12,
        },
        {
          id: 'payments',
          to: '/payment-methods',
          label: 'Pagamentos',
          icon: CreditCard,
          anyPermission: [...SHELL_PAYMENT_PERMISSIONS],
          aliases: ['/payment-methods', '/card-brands', '/card-operators'],
          order: 14,
        },
      ],
    },
  },
  {
    kind: 'group',
    group: {
      id: 'gestao',
      label: 'Gestão',
      icon: SlidersHorizontal,
      items: [
        {
          id: 'team',
          to: '/users',
          label: 'Equipe',
          icon: UserCog,
          permission: 'users.view',
          order: 13,
        },
        {
          id: 'settings',
          to: '/settings',
          label: 'Configurações',
          icon: Settings,
          anyPermission: [...SHELL_SETTINGS_PERMISSIONS],
          aliases: ['/client-origins', '/campaigns'],
          order: 15,
        },
        {
          id: 'integrations',
          to: '/integracoes',
          label: 'Integrações',
          icon: Plug,
          permission: 'integrations.manage',
          order: 16,
        },
      ],
    },
  },
]

export function canSeeNavItem(item: ShellNavItemDef, permissions: string[]): boolean {
  if (permissions.length === 0) {
    return true
  }
  if (item.anyPermission?.length) {
    return item.anyPermission.some((name) => permissions.includes(name))
  }
  return !item.permission || permissions.includes(item.permission)
}

export function resolveNavItem(item: ShellNavItemDef, paymentHome: string): ShellNavItemDef {
  if (item.id !== 'payments') {
    return item
  }
  return { ...item, to: `/${paymentHome}` }
}

export function buildVisibleNav(permissions: string[], paymentHome: string): VisibleNav[] {
  return normalizeNav(SHELL_NAV, permissions, paymentHome)
}

function normalizeNav(
  nodes: ShellNavNodeDef[],
  permissions: string[],
  paymentHome: string,
): VisibleNav[] {
  const out: VisibleNav[] = []
  for (const node of nodes) {
    if (node.kind === 'item') {
      if (!canSeeNavItem(node.item, permissions)) {
        continue
      }
      out.push({ kind: 'item', ...resolveNavItem(node.item, paymentHome) })
      continue
    }
    const items = node.group.items
      .filter((item) => canSeeNavItem(item, permissions))
      .map((item) => resolveNavItem(item, paymentHome))
    if (items.length === 0) {
      continue
    }
    if (items.length === 1) {
      out.push({ kind: 'item', ...items[0] })
      continue
    }
    out.push({
      kind: 'group',
      id: node.group.id,
      label: node.group.label,
      icon: node.group.icon,
      items,
    })
  }
  return out
}

export function flattenNavItems(nav: VisibleNav[]): ShellNavItemDef[] {
  const items: ShellNavItemDef[] = []
  for (const entry of nav) {
    if (entry.kind === 'item') {
      const { kind: _kind, ...item } = entry
      items.push(item)
    } else {
      items.push(...entry.items)
    }
  }
  return items.sort((a, b) => a.order - b.order)
}

export function omitPinnedItems(nav: VisibleNav[], pinnedIds: ReadonlySet<string>): VisibleNav[] {
  const out: VisibleNav[] = []
  for (const entry of nav) {
    if (entry.kind === 'item') {
      if (!pinnedIds.has(entry.id)) {
        out.push(entry)
      }
      continue
    }
    const items = entry.items.filter((item) => !pinnedIds.has(item.id))
    if (items.length === 0) {
      continue
    }
    if (items.length === 1) {
      out.push({ kind: 'item', ...items[0] })
      continue
    }
    out.push({ ...entry, items })
  }
  return out
}

export function splitMobileNav(nav: VisibleNav[]): {
  pins: ShellNavItemDef[]
  more: VisibleNav[]
  needsMore: boolean
} {
  const flat = flattenNavItems(nav)
  const needsMore = flat.length > MOBILE_NAV_SLOTS
  const pins = needsMore ? flat.slice(0, MOBILE_NAV_SLOTS - 1) : flat
  const more = omitPinnedItems(nav, new Set(pins.map((item) => item.id)))
  return { pins, more, needsMore }
}

export function isNavActive(pathname: string, to: string, aliases: string[] = []): boolean {
  const paths = [to, ...aliases]
  return paths.some((path) => {
    if (path === '/') {
      return pathname === '/'
    }
    return pathname === path || pathname.startsWith(`${path}/`)
  })
}

export function groupContainingPath(nav: VisibleNav[], pathname: string): string | null {
  for (const entry of nav) {
    if (entry.kind !== 'group') {
      continue
    }
    if (entry.items.some((item) => isNavActive(pathname, item.to, item.aliases))) {
      return entry.id
    }
  }
  return null
}

export type OpenGroups = Record<string, boolean>

type ReadStorage = Pick<Storage, 'getItem'>
type WriteStorage = Pick<Storage, 'setItem' | 'removeItem'>

function defaultStorage(): Storage | null {
  return typeof localStorage === 'undefined' ? null : localStorage
}

export function isOpenState(value: unknown): value is OpenGroups {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    return false
  }
  return Object.values(value).every((entry) => typeof entry === 'boolean')
}

/** Accordion exclusivo: no máximo um grupo aberto. */
export function openOnly(groupId: string | null): OpenGroups {
  return groupId ? { [groupId]: true } : {}
}

/** O grupo aberto (o primeiro na ordem do menu, se um estado antigo tiver vários). */
export function openGroupId(open: OpenGroups, nav?: VisibleNav[]): string | null {
  const ids = Object.keys(open).filter((id) => open[id])
  if (ids.length === 0) {
    return null
  }
  if (nav) {
    const inMenu = nav.find((entry) => entry.kind === 'group' && ids.includes(entry.id))
    if (inMenu) {
      return inMenu.id
    }
  }
  return ids[0] ?? null
}

/**
 * Grupo aberto salvo. Migra o formato antigo (`gc_shell_nav_open`, vários grupos
 * `{ id: true }`) para um só grupo na chave nova e apaga a antiga.
 */
export function readStoredOpenGroup(
  storage?: (ReadStorage & Partial<WriteStorage>) | null,
  nav?: VisibleNav[],
): string | null {
  try {
    const store = storage ?? defaultStorage()
    if (!store) {
      return null
    }
    const current = store.getItem(SHELL_NAV_OPEN_GROUP_STORAGE_KEY)
    if (current !== null) {
      return current === '' ? null : current
    }
    const legacy = store.getItem(SHELL_NAV_OPEN_STORAGE_KEY)
    if (!legacy) {
      return null
    }
    let parsed: unknown = null
    try {
      parsed = JSON.parse(legacy)
    } catch {
      parsed = null
    }
    const migrated = isOpenState(parsed) ? openGroupId(parsed, nav) : null
    store.setItem?.(SHELL_NAV_OPEN_GROUP_STORAGE_KEY, migrated ?? '')
    store.removeItem?.(SHELL_NAV_OPEN_STORAGE_KEY)
    return migrated
  } catch {
    return null
  }
}

export function persistOpenGroup(groupId: string | null, storage?: WriteStorage | null): void {
  try {
    const store = storage ?? defaultStorage()
    store?.setItem(SHELL_NAV_OPEN_GROUP_STORAGE_KEY, groupId ?? '')
  } catch {
    // ignore quota / private mode
  }
}

/**
 * Estado ao abrir a página: só o grupo da rota atual. Numa rota fora de grupo
 * (ex.: Início), vale o último grupo que a pessoa deixou aberto.
 */
export function initialOpenGroups(
  nav: VisibleNav[],
  pathname: string,
  stored: string | null,
): OpenGroups {
  return openOnly(groupContainingPath(nav, pathname) ?? stored)
}

/**
 * Navegou para uma rota: se ela fica em outro grupo, abre esse e fecha os outros.
 * Fora de grupo, ou dentro do mesmo grupo de antes (que a pessoa pode ter fechado),
 * mantém o que estava, já reduzido a um grupo.
 */
export function openGroupsForRoute(
  open: OpenGroups,
  nav: VisibleNav[],
  pathname: string,
  previousPathname?: string,
): OpenGroups {
  const active = groupContainingPath(nav, pathname)
  const sameGroupAsBefore =
    previousPathname !== undefined && active === groupContainingPath(nav, previousPathname)
  const keep = openGroupId(open, nav)
  const next = openOnly(active && !sameGroupAsBefore ? active : keep)
  return sameOpenGroups(open, next) ? open : next
}

/** Clique no cabeçalho: abre só este grupo; se ele já estava aberto, fecha. */
export function toggleExclusiveGroup(open: OpenGroups, groupId: string): OpenGroups {
  return open[groupId] ? {} : openOnly(groupId)
}

export function sameOpenGroups(a: OpenGroups, b: OpenGroups): boolean {
  const ids = (state: OpenGroups) =>
    Object.keys(state)
      .filter((id) => state[id])
      .sort()
      .join('|')
  return ids(a) === ids(b)
}

export function isAlertsItem(item: Pick<ShellNavItemDef, 'id'>): boolean {
  return item.id === 'alerts'
}
