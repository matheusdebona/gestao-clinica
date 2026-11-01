<script setup lang="ts">
import { computed } from 'vue'
import RankBar from '@/components/patterns/RankBar.vue'
import type { GoalMetric, GoalProgress } from '@/features/goals/api'
import { formatBRL } from '@/lib/formatters'

const props = defineProps<{ progress: GoalProgress }>()

const withTarget = computed(() => props.progress.metrics.filter((metric) => metric.target !== null))

function amount(metric: GoalMetric, value: number | string | null): string {
  if (value === null) {
    return '—'
  }
  return metric.money ? formatBRL(value) : String(value)
}

function value(metric: GoalMetric): string {
  return `${metric.percent ?? 0}%`
}

function meta(metric: GoalMetric): string {
  const progress = `${amount(metric, metric.actual)} de ${amount(metric, metric.target)}`
  const remaining = Number(metric.remaining ?? 0)
  return remaining <= 0
    ? `${progress} · Meta batida`
    : `${progress} · Faltam ${amount(metric, metric.remaining)}`
}

function ratio(metric: GoalMetric): number {
  return (metric.percent ?? 0) / 100
}
</script>

<template>
  <div class="divide-y divide-border-divider">
    <RankBar
      v-for="metric in withTarget"
      :key="metric.key"
      :label="metric.label"
      :value="value(metric)"
      :meta="meta(metric)"
      :ratio="ratio(metric)"
    />
  </div>
</template>
