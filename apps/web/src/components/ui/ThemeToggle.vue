<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue'
import { computed } from 'vue'
import IconButton from '@/components/ui/IconButton.vue'
import Tabs from '@/components/ui/Tabs.vue'
import { cycleThemePreference, type ThemePreference } from '@/lib/theme'
import { useThemeStore } from '@/stores/theme'

const props = withDefaults(
  defineProps<{
    variant?: 'icon' | 'segmented'
    tone?: 'default' | 'inverse'
  }>(),
  {
    variant: 'icon',
    tone: 'default',
  },
)

const theme = useThemeStore()

const items = [
  { value: 'system', label: 'Sistema' },
  { value: 'light', label: 'Claro' },
  { value: 'dark', label: 'Escuro' },
]

const iconLabel = computed(() => {
  if (theme.preference === 'system') {
    return 'Tema: sistema'
  }
  if (theme.preference === 'light') {
    return 'Tema: claro'
  }
  return 'Tema: escuro'
})

function onSegment(value: string) {
  theme.setPreference(value as ThemePreference)
}

function onCycle() {
  theme.setPreference(cycleThemePreference(theme.preference))
}
</script>

<template>
  <Tabs
    v-if="variant === 'segmented'"
    :model-value="theme.preference"
    block
    :items="items"
    @update:model-value="onSegment"
  />
  <IconButton
    v-else
    :label="iconLabel"
    :class="tone === 'inverse' ? '!text-inverse hover:!bg-inverse/10' : undefined"
    @click="onCycle"
  >
    <Monitor v-if="theme.preference === 'system'" class="size-4" :stroke-width="1.75" />
    <Sun v-else-if="theme.preference === 'light'" class="size-4" :stroke-width="1.75" />
    <Moon v-else class="size-4" :stroke-width="1.75" />
  </IconButton>
</template>
