<script setup lang="ts">
import { cn } from '@/lib/cn'
import { Check } from '@lucide/vue'
import { CheckboxIndicator, CheckboxRoot } from 'reka-ui'

withDefaults(
  defineProps<{
    modelValue?: boolean
    disabled?: boolean
    id?: string
    label?: string
  }>(),
  {
    modelValue: false,
    disabled: false,
    label: '',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()
</script>

<template>
  <label
    :class="cn('inline-flex cursor-pointer items-center gap-2.5 text-[15px] text-title', disabled && 'cursor-not-allowed opacity-40')"
  >
    <CheckboxRoot
      :id="id"
      :model-value="modelValue"
      :disabled="disabled"
      class="glass-field flex size-[22px] shrink-0 items-center justify-center rounded-[6px] outline-none data-[state=checked]:border-brand data-[state=checked]:bg-brand"
      @update:model-value="(v: boolean | 'indeterminate') => emit('update:modelValue', v === true)"
      @click.stop
    >
      <CheckboxIndicator>
        <Check class="size-3.5 text-inverse" :stroke-width="3" />
      </CheckboxIndicator>
    </CheckboxRoot>
    <span v-if="label || $slots.default">
      <slot>{{ label }}</slot>
    </span>
  </label>
</template>
