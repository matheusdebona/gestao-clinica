<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import GoalMetrics from '@/features/goals/GoalMetrics.vue'
import { currentMonth, getMyGoal, monthLabel } from '@/features/goals/api'

const month = currentMonth()
const { data, isPending } = useQuery({
  queryKey: ['goals', 'me', month],
  queryFn: () => getMyGoal(month),
})
</script>

<template>
  <SurfaceCard>
    <div class="flex items-baseline justify-between gap-3">
      <h2>Minha meta</h2>
      <p class="text-[13px] text-muted">{{ monthLabel(month) }}</p>
    </div>
    <div v-if="isPending" class="mt-3"><Skeleton class="h-12" /></div>
    <template v-else-if="data && data.has_goal">
      <p class="mt-1 text-[13px] text-muted">
        Ritmo esperado: {{ data.expected_percent }}% do mês já passou.
      </p>
      <div class="mt-3">
        <GoalMetrics :progress="data" />
      </div>
    </template>
    <p v-else class="mt-2 text-[15px] text-body">
      Nenhuma meta definida para este mês. A administração define as metas em Equipe.
    </p>
  </SurfaceCard>
</template>
