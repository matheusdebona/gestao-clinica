<script setup lang="ts">
import { cn } from '@/lib/cn'
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    count: number
    max?: number
  }>(),
  {
    max: 99,
  },
)

const label = computed(() => {
  if (props.count > props.max) {
    return `${props.max}+`
  }
  return String(props.count)
})

const classes = computed(() =>
  cn(
    'inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-danger px-1 text-[11px] font-semibold leading-none text-inverse tabular-nums',
  ),
)
</script>

<template>
  <span
    v-if="count > 0"
    :class="classes"
    :aria-label="`${count} não lidos`"
  >
    {{ label }}
  </span>
</template>
