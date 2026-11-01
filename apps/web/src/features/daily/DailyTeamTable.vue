<script setup lang="ts">
import { ArrowDown, ArrowUp } from '@lucide/vue'
import { computed, ref } from 'vue'
import Badge from '@/components/ui/Badge.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import type { DailyKpis, DailyTeamRow } from '@/features/daily/api'
import { sortTeamRows, type TeamSortKey } from '@/features/daily/filters'

const props = defineProps<{ rows: DailyTeamRow[]; singleDay: boolean; totals: DailyKpis }>()

const sortKey = ref<TeamSortKey>('touched')
const sortDir = ref<'asc' | 'desc'>('desc')

const columns: { key: TeamSortKey; label: string }[] = [
  { key: 'name', label: 'Usuário' },
  { key: 'registered', label: 'Cadastrou' },
  { key: 'touched', label: 'Tocou' },
  { key: 'approached', label: 'Abordou' },
  { key: 'replied', label: 'Responderam' },
  { key: 'booked', label: 'Agendaram' },
  { key: 'converted', label: 'Viraram paciente' },
  { key: 'cancelled', label: 'Cancelou' },
  { key: 'pending_follow_ups', label: 'Pendentes' },
  { key: 'checkins', label: 'Check-in' },
]

const sorted = computed(() => sortTeamRows(props.rows, sortKey.value, sortDir.value))

function sortBy(key: TeamSortKey) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortDir.value = key === 'name' ? 'asc' : 'desc'
  }
}

function value(row: DailyTeamRow, key: TeamSortKey): number | string {
  return key === 'name' ? row.user.name : row[key]
}

</script>

<template>
  <SurfaceCard :padding="false">
    <div class="px-5 pt-5">
      <h2>Equipe</h2>
      <p class="mt-1 text-[13px] text-muted">Toque no nome da coluna para ordenar.</p>
    </div>
    <div class="mt-3 overflow-x-auto px-5 pb-5">
      <table class="w-full min-w-[880px] text-left text-[13px]">
        <thead>
          <tr class="border-b border-border-divider">
            <th v-for="col in columns" :key="col.key" class="py-2 pr-3 font-medium text-muted">
              <button
                type="button"
                class="inline-flex items-center gap-1 hover:text-title"
                :aria-sort="sortKey === col.key ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'"
                @click="sortBy(col.key)"
              >
                {{ col.label }}
                <ArrowUp v-if="sortKey === col.key && sortDir === 'asc'" class="size-3.5" :stroke-width="1.75" />
                <ArrowDown v-else-if="sortKey === col.key" class="size-3.5" :stroke-width="1.75" />
              </button>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-border-divider">
          <tr v-for="row in sorted" :key="row.user.id" class="align-top">
            <td v-for="col in columns" :key="col.key" class="py-2.5 pr-3 tabular-nums text-body">
              <template v-if="col.key === 'name'">
                <span class="font-medium text-title">{{ row.user.name }}</span>
              </template>
              <template v-else-if="col.key === 'checkins'">
                <Badge v-if="singleDay" :variant="row.checkin_done ? 'success' : 'muted'">
                  {{ row.checkin_done ? 'Feito' : 'Não' }}
                </Badge>
                <span v-else>{{ row.checkins }}</span>
                <p v-if="row.checkin?.worked || row.checkin?.change" class="mt-1 max-w-[220px] whitespace-pre-wrap text-[12px] text-muted">
                  <template v-if="row.checkin?.worked">Funcionou: {{ row.checkin.worked }}</template>
                  <template v-if="row.checkin?.change"><br />Mudar: {{ row.checkin.change }}</template>
                </p>
              </template>
              <template v-else>{{ value(row, col.key) }}</template>
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="border-t border-border-divider font-medium text-title">
            <td class="py-2.5 pr-3">Clínica</td>
            <td class="py-2.5 pr-3 tabular-nums">{{ totals.registered }}</td>
            <td class="py-2.5 pr-3 tabular-nums">{{ totals.touched }}</td>
            <td class="py-2.5 pr-3 tabular-nums">{{ totals.approached }}</td>
            <td class="py-2.5 pr-3 tabular-nums">{{ totals.replied }}</td>
            <td class="py-2.5 pr-3 tabular-nums">{{ totals.booked }}</td>
            <td class="py-2.5 pr-3 tabular-nums">{{ totals.converted }}</td>
            <td class="py-2.5 pr-3 tabular-nums">{{ totals.cancelled }}</td>
            <td class="py-2.5 pr-3 tabular-nums">{{ rows.reduce((sum, row) => sum + row.pending_follow_ups, 0) }}</td>
            <td class="py-2.5 pr-3 tabular-nums">{{ rows.reduce((sum, row) => sum + row.checkins, 0) }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </SurfaceCard>
</template>
