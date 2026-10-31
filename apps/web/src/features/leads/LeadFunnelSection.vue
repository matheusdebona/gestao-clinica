<script setup lang="ts">
import { computed } from 'vue'
import RankBar from '@/components/patterns/RankBar.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { funnelLine, type LeadFunnel } from '@/features/leads/funnel'
import { rankRatio } from '@/features/metrics/chart'

const props = withDefaults(defineProps<{ funnel: LeadFunnel; showUsers?: boolean }>(), {
  showUsers: true,
})

const originMax = computed(() => Math.max(0, ...props.funnel.by_origin.map((row) => row.approached)))
const userMax = computed(() => Math.max(0, ...props.funnel.by_user.map((row) => row.approached)))
</script>

<template>
  <SurfaceCard>
    <p class="text-[13px] font-medium text-muted">Funil de abordagem: abordados → responderam → agendaram</p>
    <p class="mt-2 text-[22px] font-semibold tabular-nums text-title">{{ funnelLine(funnel.total) }}</p>
    <EmptyState
      v-if="funnel.total.approached === 0"
      class="mt-2"
      title="Nenhum lead abordado"
      description="Marque “Abordado” nos leads (ou abra o WhatsApp) para ver o funil."
    />
    <template v-else>
      <p class="mt-4 text-[13px] font-medium text-muted">Por origem</p>
      <div class="mt-2 divide-y divide-border-divider">
        <RankBar
          v-for="row in funnel.by_origin"
          :key="row.key"
          :label="row.label"
          :meta="funnelLine(row)"
          :value="row.approached === 1 ? '1 abordado' : `${row.approached} abordados`"
          :ratio="rankRatio(row.approached, originMax)"
        />
      </div>
      <template v-if="showUsers">
        <p class="mt-4 text-[13px] font-medium text-muted">Por quem abordou</p>
        <div class="mt-2 divide-y divide-border-divider">
          <RankBar
            v-for="row in funnel.by_user"
            :key="row.key"
            :label="row.label"
            :meta="funnelLine(row)"
            :value="row.approached === 1 ? '1 abordado' : `${row.approached} abordados`"
            :ratio="rankRatio(row.approached, userMax)"
          />
        </div>
      </template>
    </template>
  </SurfaceCard>
</template>
