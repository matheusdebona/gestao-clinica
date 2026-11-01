<script setup lang="ts">
import AppSheet from '@/components/ui/AppSheet.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Checkbox from '@/components/ui/Checkbox.vue'
import FormField from '@/components/ui/FormField.vue'
import {
  TIER_FILTER_NONE,
  tierBadgeVariant,
  type PatientTier,
  type TierFilterToken,
} from '@/features/clients/tiers'

const open = defineModel<boolean>('open', { default: false })

const props = defineProps<{
  tiers: PatientTier[]
  selected: TierFilterToken[]
  total: number
}>()

const emit = defineEmits<{
  change: [tokens: TierFilterToken[]]
}>()

function toggle(token: TierFilterToken, checked: boolean) {
  const next = new Set(props.selected)
  if (checked) next.add(token)
  else next.delete(token)
  emit('change', [...next])
}
</script>

<template>
  <AppSheet
    stack-footer
    v-model:open="open"
    title="Filtros"
    description="Os resultados atualizam na hora."
  >
    <FormField
      label="Faixa"
      hint="Pelo total das vendas confirmadas nos últimos 12 meses. Marque uma ou mais faixas; Todos não filtra."
    >
      <div class="flex flex-col gap-2.5">
        <Checkbox
          :model-value="selected.length === 0"
          label="Todos"
          @update:model-value="$event && emit('change', [])"
        />
        <div v-for="tier in tiers" :key="tier.id" class="flex items-center justify-between gap-3">
          <Checkbox
            :model-value="selected.includes(tier.id)"
            :label="tier.name"
            @update:model-value="toggle(tier.id, $event)"
          />
          <Badge :variant="tierBadgeVariant(tier.color)">{{ tier.range_label }}</Badge>
        </div>
        <Checkbox
          v-if="tiers.length"
          :model-value="selected.includes(TIER_FILTER_NONE)"
          label="Sem faixa"
          @update:model-value="toggle(TIER_FILTER_NONE, $event)"
        />
      </div>
    </FormField>
    <template #footer-negative>
      <Button mobile-block variant="ghost" type="button" @click="emit('change', [])">Limpar filtros</Button>
    </template>
    <template #footer>
      <Button mobile-block type="button" @click="open = false">
        Ver {{ total }} {{ total === 1 ? 'paciente' : 'pacientes' }}
      </Button>
    </template>
  </AppSheet>
</template>
