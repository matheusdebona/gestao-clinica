<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '@/lib/cn'
import { formatBRL } from '@/lib/formatters'

const props = withDefaults(
  defineProps<{
    value: string | number | null | undefined
    size?: 'md' | 'lg'
  }>(),
  {
    size: 'md',
  },
)

const classes = computed(() =>
  cn(
    'tabular-nums text-title',
    props.size === 'lg' && 'text-[22px] font-semibold tracking-[-0.03em]',
    props.size === 'md' && 'text-[15px]',
  ),
)
</script>

<template>
  <span :class="classes">{{ formatBRL(value) }}</span>
</template>
