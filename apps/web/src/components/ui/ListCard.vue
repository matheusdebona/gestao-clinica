<script setup lang="ts">
import { ChevronRight } from '@lucide/vue'
import Badge from './Badge.vue'

withDefaults(
  defineProps<{
    variant?: 'blue' | 'purple' | 'pink'
    title: string
    meta?: string
    badge?: string
    badgeVariant?: 'success' | 'purple' | 'muted' | 'warning' | 'danger'
  }>(),
  {
    variant: 'blue',
    meta: '',
    badge: '',
    badgeVariant: 'muted',
  },
)

const emit = defineEmits<{
  action: []
}>()
</script>

<template>
  <button
    type="button"
    class="flex w-full items-center gap-3 bg-transparent px-1 py-3 text-left first:pt-0 last:pb-0"
    @click="emit('action')"
  >
    <div class="min-w-0 flex-1">
      <div class="flex flex-wrap items-center gap-2">
        <p class="truncate text-[15px] font-medium text-title">{{ title }}</p>
        <Badge v-if="badge" :variant="badgeVariant">{{ badge }}</Badge>
      </div>
      <p v-if="meta" class="mt-0.5 truncate text-[13px] text-muted">{{ meta }}</p>
    </div>
    <ChevronRight class="size-4 shrink-0 text-muted" :stroke-width="1.75" aria-hidden="true" />
  </button>
</template>
