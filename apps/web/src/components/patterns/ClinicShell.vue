<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Bell, Ellipsis, LogOut } from '@lucide/vue'
import AppSheet from '@/components/ui/AppSheet.vue'
import BrandMark from '@/components/ui/BrandMark.vue'
import Button from '@/components/ui/Button.vue'
import IconButton from '@/components/ui/IconButton.vue'
import NavBadge from '@/components/ui/NavBadge.vue'
import SidebarNavItem from '@/components/ui/SidebarNavItem.vue'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'
import ShellNavEntries from '@/components/patterns/ShellNavEntries.vue'
import { getUnreadNotificationCount } from '@/features/notifications/api'
import { paymentCatalogHome } from '@/features/payments/access'
import { APP_NAME } from '@/lib/brand'
import {
  SHELL_ALERT_PERMISSIONS,
  buildVisibleNav,
  groupContainingPath,
  isAlertsItem,
  isNavActive,
  persistOpenGroups,
  readStoredOpenGroups,
  splitMobileNav,
  toggleGroupOpen,
  withActiveGroupOpen,
  type ShellNavItemDef,
} from '@/lib/shell-nav'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const items = computed(() =>
  buildVisibleNav(auth.permissions, paymentCatalogHome((name) => auth.can(name))),
)

const mobileNav = computed(() => splitMobileNav(items.value))
const moreOpen = ref(false)
const moreActive = computed(() =>
  mobileNav.value.more.some((entry) => {
    if (entry.kind === 'item') {
      return isNavActive(route.path, entry.to, entry.aliases)
    }
    return entry.items.some((item) => isNavActive(route.path, item.to, item.aliases))
  }),
)

const openGroups = ref<Record<string, boolean>>(readStoredOpenGroups())

watch(
  () => route.path,
  (path) => {
    moreOpen.value = false
    const groupId = groupContainingPath(items.value, path)
    const next = withActiveGroupOpen(openGroups.value, groupId)
    if (next !== openGroups.value) {
      openGroups.value = next
      persistOpenGroups(next)
    }
  },
  { immediate: true },
)

watch(items, (nav) => {
  const groupId = groupContainingPath(nav, route.path)
  const next = withActiveGroupOpen(openGroups.value, groupId)
  if (next !== openGroups.value) {
    openGroups.value = next
    persistOpenGroups(next)
  }
})

function onToggleGroup(groupId: string) {
  openGroups.value = toggleGroupOpen(openGroups.value, groupId)
  persistOpenGroups(openGroups.value)
}

function onNavigate(_item: ShellNavItemDef) {
  moreOpen.value = false
}

const canAlerts = computed(
  () => SHELL_ALERT_PERMISSIONS.some((name) => auth.can(name)) || auth.permissions.length === 0,
)

const { data: unreadCount } = useQuery({
  queryKey: ['notifications', 'unread-count'],
  queryFn: getUnreadNotificationCount,
  enabled: canAlerts,
})

const alertCount = computed(() => unreadCount.value ?? 0)

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
      <nav class="flex flex-1 flex-col overflow-y-auto" aria-label="Principal">
        <ShellNavEntries
          :entries="items"
          :open-groups="openGroups"
          :current-path="route.path"
          :alert-count="alertCount"
          @toggle="onToggleGroup"
        />
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
        aria-label="Principal"
      >
        <RouterLink
          v-for="item in mobileNav.pins"
          :key="item.id"
          :to="item.to"
          class="relative flex flex-col items-center gap-1 rounded-full px-2 py-1 text-[11px]"
          :class="isNavActive(route.path, item.to, item.aliases) ? 'text-brand' : 'text-muted'"
        >
          <component :is="item.icon" class="size-5" :stroke-width="1.75" />
          <span v-if="isAlertsItem(item)" class="pointer-events-none absolute right-1 top-0">
            <NavBadge :count="alertCount" />
          </span>
          {{ item.label }}
        </RouterLink>
        <button
          v-if="mobileNav.needsMore"
          type="button"
          class="relative flex flex-col items-center gap-1 rounded-full px-2 py-1 text-[11px]"
          :class="moreActive || moreOpen ? 'text-brand' : 'text-muted'"
          :aria-expanded="moreOpen"
          aria-haspopup="dialog"
          @click="moreOpen = true"
        >
          <Ellipsis class="size-5" :stroke-width="1.75" />
          Mais
        </button>
      </nav>

      <AppSheet v-model:open="moreOpen" title="Mais">
        <nav aria-label="Mais">
          <ShellNavEntries
            :entries="mobileNav.more"
            :open-groups="openGroups"
            :current-path="route.path"
            :alert-count="alertCount"
            tone="default"
            @toggle="onToggleGroup"
            @navigate="onNavigate"
          />
        </nav>
      </AppSheet>
    </div>
  </div>
</template>
