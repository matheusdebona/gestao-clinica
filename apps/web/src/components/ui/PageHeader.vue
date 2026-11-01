<script setup lang="ts">
import { ChevronLeft } from '@lucide/vue'
import { RouterLink, type RouteLocationRaw } from 'vue-router'
import { computed } from 'vue'
import ActionBar from './ActionBar.vue'
import InfoTip from './InfoTip.vue'
import { cn } from '@/lib/cn'

const props = withDefaults(
  defineProps<{
    title: string
    description?: string
    sticky?: boolean
    /** Kept for compatibility: actions always stack full width on mobile. */
    stackActions?: boolean
    /** Back link shown above the title, e.g. { label: 'Configurações', to: { name: 'settings' } }. */
    back?: { label: string; to: RouteLocationRaw }
    /** Long explanation behind an "i" next to the title (keep `description` to one short line). */
    hint?: string
  }>(),
  {
    sticky: false,
    stackActions: true,
  },
)

/** Subtitles longer than this move into the InfoTip so the header never wraps into a column. */
const SUBTITLE_MAX = 90
const tip = computed(() => {
  const long = props.description && props.description.length > SUBTITLE_MAX ? props.description : ''
  return [props.hint, long].filter(Boolean).join(' ')
})
const subtitle = computed(() =>
  props.description && props.description.length <= SUBTITLE_MAX ? props.description : '',
)
</script>

<template>
  <header
    :class="
      cn(
        'flex flex-col gap-3 md:flex-row md:items-end md:justify-between md:gap-6',
        sticky && 'glass-clear sticky top-3 z-20 -mx-2 rounded-[18px] px-4 py-3 md:top-4',
      )
    "
  >
    <div class="min-w-0 md:flex-1">
      <RouterLink
        v-if="props.back"
        :to="props.back.to"
        class="-ml-1 mb-1 inline-flex h-8 items-center gap-0.5 rounded-[10px] px-1 text-[13px] font-medium text-brand hover:underline"
      >
        <ChevronLeft class="size-4" aria-hidden="true" />
        {{ props.back.label }}
      </RouterLink>
      <div class="flex items-center gap-2">
        <h1 class="min-w-0">{{ title }}</h1>
        <InfoTip v-if="tip" :text="tip" :label="`Sobre: ${title}`" side="bottom" />
      </div>
      <p v-if="subtitle" class="mt-1 max-w-2xl text-[15px] text-muted">
        {{ subtitle }}
      </p>
    </div>
    <ActionBar v-if="$slots.actions || $slots['actions-negative']" compact class="md:shrink-0">
      <template v-if="$slots['actions-negative']" #negative>
        <slot name="actions-negative" />
      </template>
      <slot name="actions" />
    </ActionBar>
  </header>
</template>
