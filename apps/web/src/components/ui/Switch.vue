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
    :class="cn('inline-flex cursor-pointer items-center justify-between gap-3 text-[15px] text-title', disabled && 'cursor-not-allowed opacity-40')"
  >
    <span v-if="label || $slots.default">
      <slot>{{ label }}</slot>
    </span>
    <SwitchRoot
      :id="id"
      :model-value="modelValue"
      :disabled="disabled"
      class="glass-field relative h-[31px] w-[51px] shrink-0 rounded-full outline-none transition-colors data-[state=checked]:border-success data-[state=checked]:bg-success"
      @update:model-value="(v: boolean) => emit('update:modelValue', v === true)"
      @click.stop
    >
      <SwitchThumb
        class="block size-[27px] translate-x-[2px] rounded-full bg-inverse shadow-thumb transition-transform data-[state=checked]:translate-x-[22px]"
      />
    </SwitchRoot>
  </label>
</template>
