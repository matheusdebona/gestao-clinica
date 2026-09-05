<script setup lang="ts">
import { cn } from '@/lib/cn'
import { SwitchRoot, SwitchThumb } from 'reka-ui'

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
    <SwitchRoot
      :id="id"
      :checked="modelValue"
      :disabled="disabled"
      class="relative h-6 w-11 shrink-0 rounded-full bg-border-subtle outline-none transition-colors data-[state=checked]:bg-brand"
      @update:checked="(v: boolean) => emit('update:modelValue', v === true)"
    >
      <SwitchThumb
        class="block size-5 translate-x-0.5 rounded-full bg-surface shadow-card transition-transform data-[state=checked]:translate-x-[22px]"
      />
    </SwitchRoot>
    <span v-if="label || $slots.default">
      <slot>{{ label }}</slot>
    </span>
  </label>
</template>
