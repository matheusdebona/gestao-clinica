<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed } from 'vue'
import Banner from '@/components/ui/Banner.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { listCheckins, type DailyParams } from '@/features/daily/api'
import { formatIsoDate } from '@/lib/formatters'

const props = defineProps<{ params: DailyParams }>()

const { data, isPending, isError } = useQuery({
  queryKey: computed(() => ['leads', 'checkins', props.params]),
  queryFn: () => listCheckins(props.params),
})
const items = computed(() => data.value ?? [])
</script>

<template>
  <Banner v-if="isError" variant="danger" title="Check-ins">Não foi possível carregar.</Banner>
  <SurfaceCard v-else-if="isPending"><Skeleton class="h-24" /></SurfaceCard>
  <SurfaceCard v-else-if="items.length === 0" :padding="false">
    <EmptyState title="Nenhum check-in" description="Ninguém registrou a análise do dia neste período." />
  </SurfaceCard>
  <SurfaceCard v-else :padding="false">
    <ul class="divide-y divide-border-divider px-5 py-2">
      <li v-for="item in items" :key="item.id" class="py-3">
        <p class="text-[15px] font-medium text-title">
          {{ item.user?.name ?? 'Usuário' }} · {{ formatIsoDate(item.day) }}
        </p>
        <dl class="mt-1 flex flex-col gap-1.5 text-[13px]">
          <div v-if="item.worked">
            <dt class="text-muted">O que funcionou</dt>
            <dd class="whitespace-pre-wrap text-body">{{ item.worked }}</dd>
          </div>
          <div v-if="item.change">
            <dt class="text-muted">O que mudar</dt>
            <dd class="whitespace-pre-wrap text-body">{{ item.change }}</dd>
          </div>
          <div v-if="item.notes">
            <dt class="text-muted">Observações</dt>
            <dd class="whitespace-pre-wrap text-body">{{ item.notes }}</dd>
          </div>
        </dl>
      </li>
    </ul>
  </SurfaceCard>
</template>
