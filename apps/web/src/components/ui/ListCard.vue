<script setup lang="ts">
import { ChevronRight } from '@lucide/vue'
import { cn } from '@/lib/cn'
import Badge from './Badge.vue'

withDefaults(
  defineProps<{
    variant?: 'blue' | 'purple' | 'pink'
    title: string
    description?: string
    meta?: string
    badge?: string
    badgeVariant?: 'success' | 'purple' | 'muted' | 'warning' | 'danger'
    emphasis?: boolean
    showChevron?: boolean
  }>(),
  {
    variant: 'blue',
    description: '',
    meta: '',
    badge: '',
    badgeVariant: 'muted',
    emphasis: false,
    showChevron: true,
  },
)

const emit = defineEmits<{
  action: []
}>()
</script>

<template>
  <button
    type="button"
    class="flex w-full items-center gap-3 rounded-[12px] bg-transparent px-2 py-3 text-left transition-colors first:mt-0 hover:bg-surface/35"
    @click="emit('action')"
  >
    <div class="min-w-0 flex-1">
      <div class="flex flex-wrap items-center gap-2">
        <p
          :class="
            cn(
              'truncate text-[15px] text-title',
              emphasis ? 'font-semibold' : 'font-medium',
            )
          "
        >
          {{ title }}
        </p>
        <Badge v-if="badge" :variant="badgeVariant">{{ badge }}</Badge>
        <slot name="status" />
      </div>
      <p v-if="description" class="mt-0.5 line-clamp-2 text-[13px] text-body">
        {{ description }}
      </p>
      <p v-if="meta" class="mt-0.5 truncate text-[13px] text-muted">{{ meta }}</p>
    </div>
    <ChevronRight
      v-if="showChevron"
      class="size-4 shrink-0 text-muted"
      :stroke-width="1.75"
      aria-hidden="true"
    />
  </button>
</template>
