<script setup lang="ts">
import { computed } from 'vue'
import AppSheet from '@/components/ui/AppSheet.vue'
import Button from '@/components/ui/Button.vue'
import Checkbox from '@/components/ui/Checkbox.vue'
import DatePicker from '@/components/ui/DatePicker.vue'
import FormField from '@/components/ui/FormField.vue'
import Select from '@/components/ui/Select.vue'
import Tabs from '@/components/ui/Tabs.vue'
import { DAILY_PERIOD_ITEMS, type DailyFilters, type DailyPeriod } from '@/features/daily/filters'
import { useFollowUpTiers } from '@/features/leads/useFollowUpTiers'
import { useLeadCatalogs } from '@/features/leads/useLeadCatalogs'

const open = defineModel<boolean>('open', { default: false })

const props = defineProps<{
  filters: DailyFilters
  team: boolean
  users: { id: number; name: string }[]
}>()

const emit = defineEmits<{
  change: [patch: Partial<DailyFilters>]
  clear: []
}>()

const { statuses, origins } = useLeadCatalogs()
const { tiers } = useFollowUpTiers()

const originOptions = computed(() => [
  { value: '', label: 'Todas as origens' },
  ...origins.value.map((origin) => ({ value: String(origin.id), label: origin.name })),
])
const statusOptions = computed(() => [
  { value: '', label: 'Todos os status' },
  ...statuses.value.map((status) => ({ value: String(status.id), label: status.name })),
])
const tierOptions = computed(() => [
  { value: '', label: 'Todos os prazos' },
  ...tiers.value.map((tier) => ({ value: String(tier.days), label: `Parados há ${tier.days}+ dias` })),
])

function toggleUser(id: number, checked: boolean) {
  const current = new Set(props.filters.userIds)
  if (checked) {
    current.add(id)
  } else {
    current.delete(id)
  }
  emit('change', { userIds: [...current] })
}
</script>

<template>
  <AppSheet v-model:open="open" stack-footer title="Filtros" description="Os resultados atualizam na hora.">
    <div class="flex flex-col gap-5">
      <FormField label="Período">
        <div class="flex flex-col gap-3">
          <Tabs
            :model-value="filters.period"
            block
            :items="DAILY_PERIOD_ITEMS"
            @update:model-value="emit('change', { period: $event as DailyPeriod })"
          />
          <FormField v-if="filters.period === 'dia'" label="Dia" html-for="daily-filter-day">
            <DatePicker
              id="daily-filter-day"
              :model-value="filters.day"
              @update:model-value="emit('change', { day: $event })"
            />
          </FormField>
          <div v-if="filters.period === 'custom'" class="grid grid-cols-2 gap-3">
            <FormField label="De" html-for="daily-filter-from">
              <DatePicker
                id="daily-filter-from"
                :model-value="filters.from"
                @update:model-value="emit('change', { from: $event })"
              />
            </FormField>
            <FormField label="Até" html-for="daily-filter-to">
              <DatePicker
                id="daily-filter-to"
                :model-value="filters.to"
                @update:model-value="emit('change', { to: $event })"
              />
            </FormField>
          </div>
        </div>
      </FormField>

      <FormField v-if="team" label="Usuários">
        <div class="flex flex-col gap-2.5">
          <p class="text-[13px] text-muted">Nenhum marcado = toda a equipe.</p>
          <Checkbox
            v-for="user in users"
            :key="user.id"
            :model-value="filters.userIds.includes(user.id)"
            :label="user.name"
            @update:model-value="toggleUser(user.id, $event)"
          />
        </div>
      </FormField>

      <FormField label="Origem" html-for="daily-filter-origin">
        <Select
          id="daily-filter-origin"
          :model-value="filters.originId"
          :options="originOptions"
          @update:model-value="emit('change', { originId: $event })"
        />
      </FormField>

      <FormField label="Status" html-for="daily-filter-status">
        <Select
          id="daily-filter-status"
          :model-value="filters.statusId"
          :options="statusOptions"
          @update:model-value="emit('change', { statusId: $event })"
        />
      </FormField>

      <FormField label="Prazo de acompanhamento" html-for="daily-filter-tier" hint="Vale para a fila e para onde os leads pararam.">
        <Select
          id="daily-filter-tier"
          :model-value="filters.staleDays"
          :options="tierOptions"
          @update:model-value="emit('change', { staleDays: $event })"
        />
      </FormField>
    </div>
    <template #footer-negative>
      <Button mobile-block variant="ghost" type="button" @click="emit('clear')">Limpar filtros</Button>
    </template>
    <template #footer>
      <Button mobile-block type="button" @click="open = false">Ver resumo</Button>
    </template>
  </AppSheet>
</template>
