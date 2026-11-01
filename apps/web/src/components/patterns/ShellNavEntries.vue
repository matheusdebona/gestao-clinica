<script setup lang="ts">
import SidebarNavGroup from '@/components/ui/SidebarNavGroup.vue'
import SidebarNavItem from '@/components/ui/SidebarNavItem.vue'
import NavBadge from '@/components/ui/NavBadge.vue'
import {
  isAlertsItem,
  isNavActive,
  type ShellNavItemDef,
  type VisibleNav,
} from '@/lib/shell-nav'

const props = withDefaults(
  defineProps<{
    entries: VisibleNav[]
    openGroups: Record<string, boolean>
    currentPath: string
    alertCount?: number
    tone?: 'inverse' | 'default' | 'shell'
    compact?: boolean
  }>(),
  {
    alertCount: 0,
    tone: 'inverse',
    compact: false,
  },
)

const emit = defineEmits<{
  toggle: [groupId: string]
  navigate: [item: ShellNavItemDef]
}>()

function itemActive(item: ShellNavItemDef): boolean {
  return isNavActive(props.currentPath, item.to, item.aliases)
}

function groupActive(items: ShellNavItemDef[]): boolean {
  return items.some((item) => itemActive(item))
}

function groupAlertCount(items: ShellNavItemDef[]): number {
  return items.some((item) => isAlertsItem(item)) ? props.alertCount : 0
}

function onNavigate(item: ShellNavItemDef) {
  emit('navigate', item)
}
</script>

<template>
  <div class="flex flex-col gap-0.5">
    <template v-for="entry in entries" :key="entry.kind === 'item' ? entry.id : entry.id">
      <RouterLink
        v-if="entry.kind === 'item'"
        :to="entry.to"
        @click="onNavigate(entry)"
      >
        <SidebarNavItem
          :label="entry.label"
          :icon="entry.icon"
          :active="itemActive(entry)"
          :compact="compact"
          :tone="tone"
        >
          <template v-if="isAlertsItem(entry)" #badge>
            <NavBadge :count="alertCount" />
          </template>
        </SidebarNavItem>
      </RouterLink>
      <SidebarNavGroup
        v-else
        :label="entry.label"
        :icon="entry.icon"
        :open="Boolean(openGroups[entry.id])"
        :active="groupActive(entry.items)"
        :compact="compact"
        :tone="tone"
        @toggle="emit('toggle', entry.id)"
      >
        <template v-if="!openGroups[entry.id]" #badge>
          <NavBadge :count="groupAlertCount(entry.items)" />
        </template>
        <template #default="{ nested, childTone }">
          <RouterLink
            v-for="child in entry.items"
            :key="child.id"
            :to="child.to"
            @click="onNavigate(child)"
          >
            <SidebarNavItem
              :label="child.label"
              :icon="child.icon"
              :active="itemActive(child)"
              :nested="nested"
              :tone="childTone"
            >
              <template v-if="isAlertsItem(child)" #badge>
                <NavBadge :count="alertCount" />
              </template>
            </SidebarNavItem>
          </RouterLink>
        </template>
      </SidebarNavGroup>
    </template>
  </div>
</template>
