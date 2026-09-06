<script setup lang="ts">
import { formatBRL } from '@/lib/formatters'

defineProps<{
  value: string | number | null | undefined
}>()
</script>

<template>
  <span class="tabular-nums text-[15px] text-title">{{ formatBRL(value) }}</span>
</template>
