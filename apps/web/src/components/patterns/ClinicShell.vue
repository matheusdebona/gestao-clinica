<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed, ref, watch, type Component } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Bell,
  CalendarDays,
  ClipboardList,
  CreditCard,
  FileText,
  LayoutDashboard,
  Ellipsis,
  LogOut,
  Megaphone,
  Package,
  Receipt,
  Settings,
  Stethoscope,
  UserCog,
  Users,
  RotateCcw,
  ChartNoAxesCombined,
} from '@lucide/vue'
import AppSheet from '@/components/ui/AppSheet.vue'
import BrandMark from '@/components/ui/BrandMark.vue'
import Button from '@/components/ui/Button.vue'
import IconButton from '@/components/ui/IconButton.vue'
import ListCard from '@/components/ui/ListCard.vue'
import NavBadge from '@/components/ui/NavBadge.vue'
import SidebarNavItem from '@/components/ui/SidebarNavItem.vue'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'
import { getUnreadNotificationCount } from '@/features/notifications/api'
import { PAYMENT_CATALOG_PERMISSIONS, paymentCatalogHome } from '@/features/payments/access'
import { APP_NAME } from '@/lib/brand'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

type ShellNavItem = {
  to: string
  label: string
  icon: Component
  permission?: string
  anyPermission?: string[]
  /** daily = rotina da recepção/atendimento; internal = gestão/equipe (fica embaixo). */
  group: 'daily' | 'internal'
  aliases?: string[]
}

/** Alertas: estoque (products.view) e resumo de leads parados (leads.manage). */
const ALERT_PERMISSIONS = ['products.view', 'leads.manage'] as const

const SETTINGS_PERMISSIONS = ['lead_statuses.manage', 'client_origins.manage', 'campaigns.manage']

/** Max items in the mobile tab bar, including "Mais". */
const MOBILE_SLOTS = 5

const items = computed(() => {
  const paymentsHome = paymentCatalogHome((name) => auth.can(name))
  const all: ShellNavItem[] = [
    { to: '/', label: 'Início', icon: LayoutDashboard, group: 'daily' },
    { to: '/leads', label: 'Leads', icon: Megaphone, permission: 'leads.view', group: 'daily' },
    { to: '/appointments', label: 'Agenda', icon: CalendarDays, permission: 'appointments.view', group: 'daily' },
    { to: '/clients', label: 'Pacientes', icon: Users, permission: 'clients.view', group: 'daily' },
    { to: '/sales', label: 'Vendas', icon: Receipt, permission: 'sales.view', group: 'daily' },
    { to: '/budgets', label: 'Orçamentos', icon: FileText, permission: 'budgets.view', group: 'daily' },
    { to: '/retornos', label: 'Retornos', icon: RotateCcw, permission: 'sales.view', group: 'daily' },
    { to: '/treatments', label: 'Tratamentos', icon: Stethoscope, permission: 'treatments.view', group: 'daily' },
    { to: '/protocols', label: 'Protocolos', icon: ClipboardList, permission: 'protocols.view', group: 'daily' },
    { to: '/products', label: 'Produtos', icon: Package, permission: 'products.view', group: 'internal' },
    {
      to: '/notifications',
      label: 'Alertas',
      icon: Bell,
      anyPermission: [...ALERT_PERMISSIONS],
      group: 'internal',
    },
    { to: '/metrics', label: 'Métricas', icon: ChartNoAxesCombined, permission: 'metrics.view', group: 'internal' },
    { to: '/users', label: 'Equipe', icon: UserCog, permission: 'users.view', group: 'internal' },
    {
      to: `/${paymentsHome}`,
      label: 'Pagamentos',
      icon: CreditCard,
      anyPermission: [...PAYMENT_CATALOG_PERMISSIONS],
      group: 'internal',
      aliases: ['/payment-methods', '/card-brands', '/card-operators'],
    },
    {
      to: '/settings',
      label: 'Configurações',
      icon: Settings,
      anyPermission: SETTINGS_PERMISSIONS,
      group: 'internal',
      aliases: ['/client-origins', '/campaigns'],
    },
  ]

  return all.filter((item) => {
    if (auth.permissions.length === 0) {
      return true
    }
    if (item.anyPermission?.length) {
      return item.anyPermission.some((name) => auth.can(name))
    }
    return !item.permission || auth.can(item.permission)
  })
})

const dailyItems = computed(() => items.value.filter((item) => item.group === 'daily'))
const internalItems = computed(() => items.value.filter((item) => item.group === 'internal'))

const needsMore = computed(() => items.value.length > MOBILE_SLOTS)
const mobileItems = computed(() =>
  needsMore.value ? items.value.slice(0, MOBILE_SLOTS - 1) : items.value,
)
const moreItems = computed(() => (needsMore.value ? items.value.slice(MOBILE_SLOTS - 1) : []))
const moreOpen = ref(false)
const moreActive = computed(() =>
  moreItems.value.some((item) => isNavActive(item.to, item.aliases ?? [])),
)

watch(
  () => route.fullPath,
  () => {
    moreOpen.value = false
  },
)

function openFromMore(item: ShellNavItem) {
  moreOpen.value = false
  void router.push(item.to)
}

const canAlerts = computed(
  () => ALERT_PERMISSIONS.some((name) => auth.can(name)) || auth.permissions.length === 0,
)

const { data: unreadCount } = useQuery({
  queryKey: ['notifications', 'unread-count'],
  queryFn: getUnreadNotificationCount,
  enabled: canAlerts,
})

const alertCount = computed(() => unreadCount.value ?? 0)

function isNavActive(to: string, aliases: string[] = []) {
  const paths = [to, ...aliases]
  return paths.some((path) => {
    if (path === '/') {
      return route.path === '/'
    }
    return route.path === path || route.path.startsWith(`${path}/`)
  })
}

function openAlerts() {
  void router.push({ name: 'notifications' })
}

async function onLogout() {
  await auth.logout()
  await router.push('/login')
}
</script>

<template>
  <div class="relative flex min-h-dvh">
    <div
      class="pointer-events-none hidden shrink-0 md:block"
      :style="{ width: 'calc(var(--sv-sidebar-width) + var(--sv-chrome-inset) * 2)' }"
      aria-hidden="true"
    />
    <aside
      class="glass-dark fixed z-30 hidden flex-col overflow-hidden rounded-chrome px-3 py-5 text-inverse md:flex"
      :style="{
        left: 'var(--sv-chrome-inset)',
        top: 'var(--sv-chrome-inset)',
        bottom: 'var(--sv-chrome-inset)',
        width: 'var(--sv-sidebar-width)',
      }"
    >
      <div class="px-3 pb-5">
        <BrandMark size="md" tone="inverse" />
        <p class="mt-4 text-[13px] text-inverse/50">Clínica</p>
        <p class="mt-0.5 truncate text-[15px] font-semibold tracking-[-0.02em]">
          {{ auth.clinicName || APP_NAME }}
        </p>
      </div>
      <nav class="flex flex-1 flex-col gap-0.5 overflow-y-auto">
        <RouterLink
          v-for="item in dailyItems"
          :key="item.to"
          :to="item.to"
        >
          <SidebarNavItem
            :label="item.label"
            :icon="item.icon"
            :active="isNavActive(item.to, item.aliases ?? [])"
          />
        </RouterLink>
        <p v-if="internalItems.length" class="px-3 pt-4 pb-1 text-[13px] text-inverse/50">Gestão</p>
        <RouterLink
          v-for="item in internalItems"
          :key="item.to"
          :to="item.to"
        >
          <SidebarNavItem
            :label="item.label"
            :icon="item.icon"
            :active="isNavActive(item.to, item.aliases ?? [])"
          >
            <template v-if="item.to === '/notifications'" #badge>
              <NavBadge :count="alertCount" />
            </template>
          </SidebarNavItem>
        </RouterLink>
      </nav>
      <div class="mt-4 flex items-center gap-1">
        <button type="button" class="min-w-0 flex-1 text-left" @click="onLogout">
          <SidebarNavItem label="Sair" :icon="LogOut" />
        </button>
        <ThemeToggle tone="inverse" />
      </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
      <header
        class="glass-regular sticky top-3 z-20 mx-3 mt-3 flex items-center justify-between rounded-full px-4 py-2 md:hidden"
      >
        <p class="truncate text-[15px] font-medium text-title">
          {{ auth.clinicName || APP_NAME }}
        </p>
        <div class="flex items-center gap-1">
          <ThemeToggle />
          <div v-if="canAlerts" class="relative">
            <IconButton label="Alertas" @click="openAlerts">
              <Bell class="size-5" :stroke-width="1.75" />
            </IconButton>
            <span class="pointer-events-none absolute right-0 top-0">
              <NavBadge :count="alertCount" />
            </span>
          </div>
          <Button variant="ghost" @click="onLogout">Sair</Button>
        </div>
      </header>
      <main class="flex-1 px-5 py-8 pb-28 md:px-8 md:pb-8">
        <RouterView />
      </main>

      <nav
        class="glass-regular fixed inset-x-3 bottom-3 z-30 flex justify-around rounded-full px-2 py-2 md:hidden"
      >
        <RouterLink
          v-for="item in mobileItems"
          :key="item.to"
          :to="item.to"
          class="relative flex flex-col items-center gap-1 rounded-full px-2 py-1 text-[11px]"
          :class="isNavActive(item.to, item.aliases ?? []) ? 'text-brand' : 'text-muted'"
        >
          <component :is="item.icon" class="size-5" :stroke-width="1.75" />
          <span
            v-if="item.to === '/notifications'"
            class="pointer-events-none absolute right-1 top-0"
          >
            <NavBadge :count="alertCount" />
          </span>
          {{ item.label }}
        </RouterLink>
        <button
          v-if="needsMore"
          type="button"
          class="relative flex flex-col items-center gap-1 rounded-full px-2 py-1 text-[11px]"
          :class="moreActive || moreOpen ? 'text-brand' : 'text-muted'"
          :aria-expanded="moreOpen"
          @click="moreOpen = true"
        >
          <Ellipsis class="size-5" :stroke-width="1.75" />
          Mais
        </button>
      </nav>

      <AppSheet v-model:open="moreOpen" title="Mais">
        <div class="divide-y divide-border-divider">
          <ListCard
            v-for="item in moreItems"
            :key="item.to"
            :title="item.label"
            :badge="item.to === '/notifications' && alertCount ? String(alertCount) : ''"
            badge-variant="danger"
            @action="openFromMore(item)"
          />
        </div>
      </AppSheet>
    </div>
  </div>
</template>
