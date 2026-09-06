<script setup lang="ts">
import { cn } from '@/lib/cn'

export interface RadioOption {
  value: string
  label: string
}

const model = defineModel<string>({ default: '' })

withDefaults(
  defineProps<{
    options: RadioOption[]
    disabled?: boolean
    name?: string
    invalid?: boolean
    id?: string
  }>(),
  {
    disabled: false,
    invalid: false,
    name: 'radio',
  },
)
</script>

<template>
  <div
    role="radiogroup"
    class="flex flex-col gap-2.5"
    :aria-invalid="invalid || undefined"
    :aria-disabled="disabled || undefined"
  >
    <label
      v-for="(option, index) in options"
      :key="option.value"
      :class="
        cn(
          'inline-flex cursor-pointer items-center gap-2.5 text-[15px] text-title',
          disabled && 'cursor-not-allowed opacity-40',
        )
      "
    >
      <span class="relative inline-flex size-[22px] shrink-0 items-center justify-center">
        <input
          :id="id && index === 0 ? id : undefined"
          v-model="model"
          type="radio"
          class="peer sr-only"
          :name="name"
          :value="option.value"
          :disabled="disabled"
        >
        <span
          :class="
            cn(
              'glass-field flex size-[22px] items-center justify-center rounded-full peer-focus-visible:border-brand/40',
              model === option.value && 'border-brand bg-brand',
            )
          "
          aria-hidden="true"
        >
          <span
            v-if="model === option.value"
            class="size-2 rounded-full bg-inverse"
          />
        </span>
      </span>
      <span>{{ option.label }}</span>
    </label>
  </div>
</template>
