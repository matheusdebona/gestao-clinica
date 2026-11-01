<script setup lang="ts">
import { ChevronDown } from '@lucide/vue'
import { computed, useId } from 'vue'
import type { Component } from 'vue'
import SidebarNavItem from './SidebarNavItem.vue'

const props = withDefaults(
  defineProps<{
    label: string
    open?: boolean
    active?: boolean
    compact?: boolean
    tone?: 'inverse' | 'default' | 'shell'
    icon?: Component
  }>(),
  {
    open: false,
    active: false,
    compact: false,
    tone: 'inverse',
  },
)

const emit = defineEmits<{
  toggle: []
}>()

const panelId = useId()

const headerActive = computed(() => props.active && (!props.open || props.compact))

function onTriggerKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && props.open) {
    event.preventDefault()
    emit('toggle')
  }
}
</script>

<template>
  <div class="relative" @keydown="onTriggerKeydown">
    <button
      type="button"
      class="w-full"
      :aria-expanded="open"
      :aria-controls="panelId"
      :title="compact ? label : undefined"
      @click="emit('toggle')"
    >
      <SidebarNavItem
        :label="label"
        :icon="icon"
        :active="headerActive"
        :compact="compact"
        :tone="tone"
      >
        <template #badge>
          <slot name="badge" />
          <ChevronDown
            v-if="!compact"
            class="size-4 shrink-0 transition-transform duration-150"
            :class="open ? 'rotate-0' : '-rotate-90'"
            :stroke-width="1.75"
            aria-hidden="true"
          />
        </template>
      </SidebarNavItem>
    </button>
    <div
      v-if="open"
      :id="panelId"
      role="group"
      :aria-label="label"
      :class="
        compact
          ? 'glass-menu absolute left-full top-0 z-50 ml-2 min-w-[13.5rem] p-1'
          : 'flex flex-col gap-0.5'
      "
    >
      <slot :nested="!compact" :child-tone="compact ? 'default' : tone" />
    </div>
  </div>
</template>
