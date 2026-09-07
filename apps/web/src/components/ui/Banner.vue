<script setup lang="ts">
import { cn } from '@/lib/cn'
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    variant?: 'info' | 'warning' | 'danger' | 'success'
    title?: string
  }>(),
  {
    variant: 'info',
    title: '',
  },
)

const labelClass = computed(() => {
  if (props.variant === 'success') return 'text-success'
  if (props.variant === 'warning') return 'text-warning'
  if (props.variant === 'danger') return 'text-danger'
  return 'text-brand'
})

const surfaceClass = computed(() => {
  if (props.variant === 'success') return 'glass-chip-success'
  if (props.variant === 'warning') return 'glass-chip-warning'
  if (props.variant === 'danger') return 'glass-chip-danger'
  return 'glass-chip-brand'
})
</script>

<template>
  <div
    :class="cn('glass-chip flex gap-3 rounded-[14px] px-3.5 py-3', surfaceClass)"
    role="status"
  >
    <div class="min-w-0">
      <p v-if="title" :class="['text-[15px] font-medium', labelClass]">{{ title }}</p>
      <p class="text-[15px] text-body">
        <slot />
      </p>
    </div>
  </div>
</template>
