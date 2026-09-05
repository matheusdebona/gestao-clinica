<script setup lang="ts">
import { cn } from '@/lib/cn'
import { ArrowRight } from '@lucide/vue'
import { computed } from 'vue'
import Badge from './Badge.vue'

const props = withDefaults(
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
    badgeVariant: 'purple',
  },
)

const emit = defineEmits<{
  action: []
}>()

const bg = computed(() => {
  switch (props.variant) {
    case 'purple':
      return 'bg-card-purple'
    case 'pink':
      return 'bg-card-pink'
    default:
      return 'bg-card-blue'
  }
})
</script>

<template>
  <div
    :class="cn('flex items-center gap-3 rounded-lg px-4 py-3', bg)"
  >
    <div class="min-w-0 flex-1">
      <div class="flex flex-wrap items-center gap-2">
        <p class="truncate text-sm font-semibold text-title">{{ title }}</p>
        <Badge v-if="badge" :variant="badgeVariant">{{ badge }}</Badge>
      </div>
      <p v-if="meta" class="mt-0.5 truncate text-caption text-muted">{{ meta }}</p>
    </div>
    <button
      type="button"
      class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-surface text-brand shadow-card transition-colors hover:bg-brand-light"
      aria-label="Abrir"
      @click="emit('action')"
    >
      <ArrowRight class="size-4" />
    </button>
  </div>
</template>

<style scoped>
.text-caption {
  font-size: var(--sv-text-caption);
  line-height: var(--sv-leading-caption);
}
</style>
