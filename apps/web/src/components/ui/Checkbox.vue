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
    :class="cn('inline-flex cursor-pointer items-center gap-2.5 text-sm text-body', disabled && 'cursor-not-allowed opacity-60')"
  >
    <CheckboxRoot
      :id="id"
      :checked="modelValue"
      :disabled="disabled"
      class="flex size-5 shrink-0 items-center justify-center rounded-sm border border-border-subtle bg-input shadow-input outline-none data-[state=checked]:border-brand data-[state=checked]:bg-brand"
      @update:checked="(v: boolean | 'indeterminate') => emit('update:modelValue', v === true)"
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
