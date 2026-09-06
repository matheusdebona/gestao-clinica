<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Bell,
  CalendarDays,
  ClipboardList,
  FileText,
  LayoutDashboard,
  LogOut,
  Package,
  Receipt,
  Stethoscope,
  UserCog,
  Users,
  ChartNoAxesCombined,
} from '@lucide/vue'
import Button from '@/components/ui/Button.vue'
import IconButton from '@/components/ui/IconButton.vue'
import NavBadge from '@/components/ui/NavBadge.vue'
import SidebarNavItem from '@/components/ui/SidebarNavItem.vue'
import { getUnreadNotificationCount } from '@/features/notifications/api'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const items = computed(() => {
  const all = [
    { to: '/', label: 'Início', icon: LayoutDashboard, pinMobile: true },
    { to: '/clients', label: 'Clientes', icon: Users, permission: 'clients.view', pinMobile: true },
    { to: '/users', label: 'Equipe', icon: UserCog, permission: 'users.view' },
    { to: '/products', label: 'Produtos', icon: Package, permission: 'products.view', pinMobile: true },
    { to: '/protocols', label: 'Protocolos', icon: ClipboardList, permission: 'protocols.view' },
    { to: '/sales', label: 'Vendas', icon: Receipt, permission: 'sales.view', pinMobile: true },
    { to: '/budgets', label: 'Orçamentos', icon: FileText, permission: 'budgets.view' },
    { to: '/appointments', label: 'Agenda', icon: CalendarDays, permission: 'appointments.view', pinMobile: true },
    { to: '/treatments', label: 'Tratamentos', icon: Stethoscope, permission: 'treatments.view' },
    { to: '/notifications', label: 'Alertas', icon: Bell, permission: 'products.view' },
    { to: '/metrics', label: 'Métricas', icon: ChartNoAxesCombined, permission: 'metrics.view' },
  ]

  return all.filter((item) => !item.permission || auth.can(item.permission) || auth.permissions.length === 0)
})

const mobileItems = computed(() => {
  const pinned = items.value.filter((item) => item.pinMobile)
  const rest = items.value.filter((item) => !item.pinMobile)
  return [...pinned, ...rest].slice(0, 5)
})

const canAlerts = computed(() => auth.can('products.view') || auth.permissions.length === 0)

const { data: unreadCount } = useQuery({
  queryKey: ['notifications', 'unread-count'],
  queryFn: getUnreadNotificationCount,
  enabled: canAlerts,
})

const alertCount = computed(() => unreadCount.value ?? 0)

function isNavActive(to: string) {
  if (to === '/') {
    return route.path === '/'
  }
  return route.path === to || route.path.startsWith(`${to}/`)
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
        <p class="text-[13px] text-inverse/50">Clínica</p>
        <p class="mt-0.5 truncate text-[15px] font-semibold tracking-[-0.02em]">
          {{ auth.clinicName || 'Gestão' }}
        </p>
      </div>
      <nav class="flex flex-1 flex-col gap-0.5 overflow-y-auto">
        <RouterLink
          v-for="item in items"
          :key="item.to"
          :to="item.to"
        >
          <SidebarNavItem
            :label="item.label"
            :icon="item.icon"
            :active="isNavActive(item.to)"
          >
            <template v-if="item.to === '/notifications'" #badge>
              <NavBadge :count="alertCount" />
            </template>
          </SidebarNavItem>
        </RouterLink>
      </nav>
      <button type="button" class="mt-4 w-full text-left" @click="onLogout">
        <SidebarNavItem label="Sair" :icon="LogOut" />
      </button>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
      <header
        class="glass-regular sticky top-3 z-20 mx-3 mt-3 flex items-center justify-between rounded-full px-4 py-2 md:hidden"
      >
        <p class="truncate text-[15px] font-medium text-title">
          {{ auth.clinicName || 'Gestão' }}
        </p>
        <div class="flex items-center gap-1">
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
          :class="isNavActive(item.to) ? 'text-brand' : 'text-muted'"
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
      </nav>
    </div>
  </div>
</template>
