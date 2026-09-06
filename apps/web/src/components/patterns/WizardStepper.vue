<script setup lang="ts">
import Button from '@/components/ui/Button.vue'

export interface WizardStep {
  id: string
  label: string
}

const props = defineProps<{
  steps: WizardStep[]
  current: number
}>()

const emit = defineEmits<{
  select: [index: number]
}>()

function onSelect(index: number) {
  if (index <= props.current) {
    emit('select', index)
  }
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <p class="text-[13px] text-muted">
      Passo {{ current + 1 }} de {{ steps.length }}
    </p>
    <p class="text-[17px] font-semibold tracking-[-0.02em] text-title">
      {{ steps[current]?.label }}
    </p>
    <div class="flex flex-wrap gap-2">
      <Button
        v-for="(step, index) in steps"
        :key="step.id"
        :variant="index === current ? 'primary' : 'secondary'"
        :disabled="index > current"
        type="button"
        @click="onSelect(index)"
      >
        {{ index + 1 }}. {{ step.label }}
      </Button>
    </div>
  </div>
</template>
