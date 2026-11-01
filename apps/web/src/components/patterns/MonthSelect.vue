<script setup lang="ts">
import { computed } from 'vue'
import Select from '@/components/ui/Select.vue'
import { formatMonthLabel } from '@/features/finance/labels'

/** Seleção de mês de competência (AAAA-MM). */
const model = defineModel<string>({ required: true })

const props = withDefaults(
  defineProps<{
    id?: string
    invalid?: boolean
    /** Meses para trás a partir do mês atual. */
    past?: number
    /** Meses para a frente a partir do mês atual. */
    future?: number
  }>(),
  { id: undefined, invalid: false, past: 36, future: 12 },
)

function shift(base: Date, months: number): string {
  const d = new Date(base.getFullYear(), base.getMonth() + months, 1)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

const options = computed(() => {
  const now = new Date()
  const values = new Set<string>()
  for (let i = props.future; i >= -props.past; i -= 1) {
    values.add(shift(now, i))
  }
  if (model.value) values.add(model.value)
  return [...values]
    .sort((a, b) => b.localeCompare(a))
    .map((value) => ({ value, label: formatMonthLabel(value) }))
})
</script>

<template>
  <Select :id="props.id" v-model="model" :options="options" :invalid="props.invalid" />
</template>
