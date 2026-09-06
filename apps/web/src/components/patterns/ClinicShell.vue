<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Bell,
  LayoutDashboard,
  LogOut,
  Package,
  Receipt,
  Stethoscope,
  Users,
  ChartNoAxesCombined,
} from '@lucide/vue'
import Button from '@/components/ui/Button.vue'
import SidebarNavItem from '@/components/ui/SidebarNavItem.vue'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const items = computed(() => {
  const all = [
    { to: '/', label: 'Início', icon: LayoutDashboard },
    { to: '/clients', label: 'Clientes', icon: Users, permission: 'clients.view' },
    { to: '/products', label: 'Produtos', icon: Package, permission: 'products.view' },
    { to: '/sales', label: 'Vendas', icon: Receipt, permission: 'sales.view' },
    { to: '/treatments', label: 'Tratamentos', icon: Stethoscope, permission: 'treatments.view' },
    { to: '/notifications', label: 'Alertas', icon: Bell },
    { to: '/metrics', label: 'Métricas', icon: ChartNoAxesCombined, permission: 'metrics.view' },
  ]

  return all.filter((item) => !item.permission || auth.can(item.permission) || auth.permissions.length === 0)
})

function isNavActive(to: string) {
  if (to === '/') {
    return route.path === '/'
  }
  return route.path === to || route.path.startsWith(`${to}/`)
}

async function onLogout() {
  await auth.logout()
  await router.push('/login')
}
</script>

<template>
  <div class="flex min-h-screen bg-canvas">
    <aside
      class="hidden w-60 shrink-0 flex-col bg-sidebar px-3 py-6 text-inverse md:flex"
    >
      <div class="px-3 pb-6">
        <p class="text-[13px] text-white/50">Clínica</p>
        <p class="mt-0.5 truncate text-[15px] font-semibold tracking-[-0.02em]">
          {{ auth.clinicName || 'Gestão' }}
        </p>
      </div>
      <nav class="flex flex-1 flex-col gap-0.5">
        <RouterLink
          v-for="item in items"
          :key="item.to"
          :to="item.to"
        >
          <SidebarNavItem
            :label="item.label"
            :icon="item.icon"
            :active="isNavActive(item.to)"
          />
        </RouterLink>
      </nav>
      <button type="button" class="mt-4 w-full text-left" @click="onLogout">
        <SidebarNavItem label="Sair" :icon="LogOut" />
      </button>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
      <header class="flex items-center justify-between px-5 pt-5 md:hidden">
        <p class="truncate text-[15px] font-medium text-title">
          {{ auth.clinicName || 'Gestão' }}
        </p>
        <Button variant="ghost" @click="onLogout">Sair</Button>
      </header>
      <main class="flex-1 px-5 py-8 pb-24 md:px-8 md:pb-8">
        <RouterView />
      </main>

      <nav
        class="fixed inset-x-0 bottom-0 z-30 flex justify-around border-t border-border-subtle bg-surface px-2 py-2 md:hidden"
      >
        <RouterLink
          v-for="item in items.slice(0, 5)"
          :key="item.to"
          :to="item.to"
          class="flex flex-col items-center gap-1 px-2 py-1 text-[11px]"
          :class="isNavActive(item.to) ? 'text-brand' : 'text-muted'"
        >
          <component :is="item.icon" class="size-5" :stroke-width="1.75" />
          {{ item.label }}
        </RouterLink>
      </nav>
    </div>
  </div>
</template>
