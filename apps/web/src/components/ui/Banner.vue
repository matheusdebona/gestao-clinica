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

const classes = computed(() =>
  cn(
    'flex gap-3 rounded-[10px] px-3.5 py-3 text-[15px]',
    props.variant === 'info' && 'bg-brand-light text-title',
    props.variant === 'success' && 'bg-success-light text-title',
    props.variant === 'warning' && 'bg-warning-light text-title',
    props.variant === 'danger' && 'bg-danger-light text-title',
  ),
)

const labelClass = computed(() => {
  if (props.variant === 'success') return 'text-success'
  if (props.variant === 'warning') return 'text-warning'
  if (props.variant === 'danger') return 'text-danger'
  return 'text-brand'
})
</script>

<template>
  <div :class="classes" role="status">
    <div class="min-w-0">
      <p v-if="title" :class="['font-medium', labelClass]">{{ title }}</p>
      <p :class="title ? 'mt-0.5 text-body' : 'text-body'">
        <slot />
      </p>
    </div>
  </div>
</template>
