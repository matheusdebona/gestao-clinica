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

export const SHELL_NAV_OPEN_STORAGE_KEY = 'gc_shell_nav_open'

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

export function isOpenState(value: unknown): value is Record<string, boolean> {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    return false
  }
  return Object.values(value).every((entry) => typeof entry === 'boolean')
}

export function readStoredOpenGroups(
  storage?: Pick<Storage, 'getItem'> | null,
): Record<string, boolean> {
  try {
    const store = storage ?? (typeof localStorage === 'undefined' ? null : localStorage)
    const raw = store?.getItem(SHELL_NAV_OPEN_STORAGE_KEY)
    if (!raw) {
      return {}
    }
    const parsed: unknown = JSON.parse(raw)
    return isOpenState(parsed) ? parsed : {}
  } catch {
    return {}
  }
}

export function persistOpenGroups(
  state: Record<string, boolean>,
  storage?: Pick<Storage, 'setItem'> | null,
): void {
  try {
    const store = storage ?? (typeof localStorage === 'undefined' ? null : localStorage)
    store?.setItem(SHELL_NAV_OPEN_STORAGE_KEY, JSON.stringify(state))
  } catch {
    // ignore quota / private mode
  }
}

export function withActiveGroupOpen(
  open: Record<string, boolean>,
  groupId: string | null,
): Record<string, boolean> {
  if (!groupId || open[groupId]) {
    return open
  }
  return { ...open, [groupId]: true }
}

export function toggleGroupOpen(
  open: Record<string, boolean>,
  groupId: string,
): Record<string, boolean> {
  return { ...open, [groupId]: !open[groupId] }
}

export function isAlertsItem(item: Pick<ShellNavItemDef, 'id'>): boolean {
  return item.id === 'alerts'
}
