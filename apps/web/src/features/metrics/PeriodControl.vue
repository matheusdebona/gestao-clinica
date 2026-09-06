<script setup lang="ts">
import { computed, ref } from 'vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import Tabs from '@/components/ui/Tabs.vue'
import { PERIOD_ITEMS } from '@/features/metrics/labels'
import {
  periodError,
  presetForRange,
  rangeForPreset,
  type PeriodPreset,
} from '@/features/metrics/period'

const from = defineModel<string>('from', { required: true })
const to = defineModel<string>('to', { required: true })

const customOpen = ref(false)

const preset = computed(() => {
  if (customOpen.value) {
    return 'custom'
  }
  return presetForRange(from.value, to.value)
})

const error = computed(() => periodError(from.value, to.value))
const showDates = computed(() => preset.value === 'custom')

function onPreset(value: string) {
  if (value === 'custom') {
    customOpen.value = true
    return
  }
  customOpen.value = false
  const range = rangeForPreset(value as PeriodPreset)
  from.value = range.from
  to.value = range.to
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <Tabs :model-value="preset" block :items="PERIOD_ITEMS" @update:model-value="onPreset" />
    <div v-if="showDates" class="grid grid-cols-2 gap-3">
      <FormField label="De" html-for="metrics-from">
        <Input id="metrics-from" v-model="from" type="date" :invalid="Boolean(error)" />
      </FormField>
      <FormField label="Até" html-for="metrics-to" :error="error">
        <Input id="metrics-to" v-model="to" type="date" :invalid="Boolean(error)" />
      </FormField>
    </div>
  </div>
</template>
