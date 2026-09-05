<script setup lang="ts">
import { cn } from '@/lib/cn'
import { LoaderCircle } from '@lucide/vue'
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    size?: 'sm' | 'md'
    label?: string
  }>(),
  {
    size: 'md',
    label: 'Carregando',
  },
)

const classes = computed(() =>
  cn(
    'animate-spin text-brand',
    props.size === 'sm' ? 'size-4' : 'size-6',
  ),
)
</script>

<template>
  <LoaderCircle :class="classes" :aria-label="label" role="status" />
</template>
