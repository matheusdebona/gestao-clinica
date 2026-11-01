<script setup lang="ts">
import { computed } from 'vue'
import AppSheet from '@/components/ui/AppSheet.vue'
import Button from '@/components/ui/Button.vue'
import Checkbox from '@/components/ui/Checkbox.vue'
import DatePicker from '@/components/ui/DatePicker.vue'
import FormField from '@/components/ui/FormField.vue'
import Select from '@/components/ui/Select.vue'
import Tabs from '@/components/ui/Tabs.vue'
import {
  LEAD_CONVERTED_ITEMS,
  LEAD_PERIOD_ITEMS,
  staleFilterOptions,
  type LeadConvertedFilter,
  type LeadFilters,
  type LeadPeriod,
} from '@/features/leads/filters'
import { useFollowUpTiers } from '@/features/leads/useFollowUpTiers'
import type { ClientOrigin } from '@/types/client'
import type { LeadStatus, LeadUserRef } from '@/types/lead'

const open = defineModel<boolean>('open', { default: false })

const props = defineProps<{
  filters: LeadFilters
  statuses: LeadStatus[]
  origins: ClientOrigin[]
  creators: LeadUserRef[]
  total: number
}>()

const emit = defineEmits<{
  change: [patch: Partial<LeadFilters>]
  clear: []
}>()

const originOptions = computed(() => [
  { value: '', label: 'Todas as origens' },
  ...props.origins.map((origin) => ({
    value: String(origin.id),
    label: origin.is_active ? origin.name : `${origin.name} (inativa)`,
  })),
])

const { tiers } = useFollowUpTiers()
const staleOptions = computed(() => staleFilterOptions(tiers.value))

const creatorOptions = computed(() => [
  { value: '', label: 'Todos os usuários' },
  ...props.creators.map((user) => ({
    value: String(user.id),
    label: user.name,
  })),
])

const periodError = computed(() => {
  const { from, to } = props.filters
  if (props.filters.period === 'custom' && from && to && from > to) {
    return 'A data final deve ser igual ou depois da inicial.'
  }
  return ''
})

function toggleStatus(id: number, checked: boolean) {
  const current = new Set(props.filters.statusIds)
  if (checked) {
    current.add(id)
  } else {
    current.delete(id)
  }
  emit('change', { statusIds: [...current] })
}
</script>

<template>
  <AppSheet
    stack-footer
    v-model:open="open"
    title="Filtros"
    description="Os resultados atualizam na hora."
  >
    <div class="flex flex-col gap-5">
      <FormField label="Período de cadastro">
        <div class="flex flex-col gap-3">
          <Tabs
            :model-value="filters.period"
            block
            :items="LEAD_PERIOD_ITEMS.slice(0, 3)"
            @update:model-value="emit('change', { period: $event as LeadPeriod })"
          />
          <Tabs
            :model-value="filters.period"
            block
            :items="LEAD_PERIOD_ITEMS.slice(3)"
            @update:model-value="emit('change', { period: $event as LeadPeriod })"
          />
          <div v-if="filters.period === 'custom'" class="grid grid-cols-2 gap-3">
            <FormField label="De" html-for="lead-filter-from">
              <DatePicker
                id="lead-filter-from"
                :model-value="filters.from"
                :invalid="Boolean(periodError)"
                @update:model-value="emit('change', { from: $event })"
              />
            </FormField>
            <FormField label="Até" html-for="lead-filter-to" :error="periodError">
              <DatePicker
                id="lead-filter-to"
                :model-value="filters.to"
                :invalid="Boolean(periodError)"
                @update:model-value="emit('change', { to: $event })"
              />
            </FormField>
          </div>
        </div>
      </FormField>

      <FormField label="Status">
        <div class="flex flex-col gap-2.5">
          <Checkbox
            v-for="status in statuses"
            :key="status.id"
            :model-value="filters.statusIds.includes(status.id)"
            :label="status.is_active ? status.name : `${status.name} (inativo)`"
            @update:model-value="toggleStatus(status.id, $event)"
          />
        </div>
      </FormField>

      <FormField label="Origem" html-for="lead-filter-origin">
        <Select
          id="lead-filter-origin"
          :model-value="filters.originId"
          :options="originOptions"
          @update:model-value="emit('change', { originId: $event })"
        />
      </FormField>

      <FormField label="Cadastrado por" html-for="lead-filter-creator">
        <Select
          id="lead-filter-creator"
          :model-value="filters.createdBy"
          :options="creatorOptions"
          @update:model-value="emit('change', { createdBy: $event })"
        />
      </FormField>

      <FormField label="Acompanhamento" html-for="lead-filter-stale">
        <Select
          id="lead-filter-stale"
          :model-value="filters.stale"
          :options="staleOptions"
          @update:model-value="emit('change', { stale: $event as LeadFilters['stale'] })"
        />
      </FormField>

      <FormField label="Conversão">
        <Tabs
          :model-value="filters.converted"
          block
          :items="LEAD_CONVERTED_ITEMS"
          @update:model-value="
            emit('change', {
              converted: $event as LeadConvertedFilter,
            })
          "
        />
      </FormField>
    </div>
    <template #footer-negative>
      <Button mobile-block variant="ghost" type="button" @click="emit('clear')"
        >Limpar filtros</Button
      >
    </template>
    <template #footer>
      <Button mobile-block type="button" @click="open = false">
        Ver {{ total }} {{ total === 1 ? 'lead' : 'leads' }}
      </Button>
    </template>
  </AppSheet>
</template>
