<script setup lang="ts">
import { cn } from '@/lib/cn'
import { Check, ChevronDown } from '@lucide/vue'
import {
  SelectContent,
  SelectItem,
  SelectItemIndicator,
  SelectPortal,
  SelectRoot,
  SelectTrigger,
  SelectValue,
  SelectViewport,
} from 'reka-ui'
import { computed } from 'vue'

interface SelectOption {
  value: string
  label: string
}

const props = withDefaults(
  defineProps<{
    modelValue?: string
    options: SelectOption[]
    placeholder?: string
    invalid?: boolean
    disabled?: boolean
    id?: string
  }>(),
  {
    modelValue: '',
    placeholder: 'Selecionar…',
    invalid: false,
    disabled: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const triggerClass = computed(() =>
  cn(
    'inline-flex w-full items-center justify-between gap-2 rounded-[10px] border bg-input px-3.5 py-2.5 text-sm text-title shadow-input outline-none transition-colors disabled:cursor-not-allowed disabled:opacity-60',
    props.invalid
      ? 'border-danger focus-visible:outline-danger'
      : 'border-transparent focus-visible:border-brand data-[state=open]:border-brand',
  ),
)
</script>

<template>
  <SelectRoot
    :model-value="modelValue || undefined"
    :disabled="disabled"
    @update:model-value="(v) => emit('update:modelValue', String(v ?? ''))"
  >
    <SelectTrigger :id="id" :class="triggerClass" :aria-invalid="invalid || undefined">
      <SelectValue :placeholder="placeholder" class="truncate text-left" />
      <ChevronDown class="size-4 shrink-0 text-muted" aria-hidden="true" />
    </SelectTrigger>
    <SelectPortal>
      <SelectContent
        class="z-50 overflow-hidden rounded-md border border-border-subtle bg-surface shadow-floating"
        :side-offset="6"
        position="popper"
      >
        <SelectViewport class="p-1.5">
          <SelectItem
            v-for="option in options"
            :key="option.value"
            :value="option.value"
            class="relative flex cursor-pointer select-none items-center rounded-sm py-2 pr-8 pl-3 text-sm text-body outline-none data-[highlighted]:bg-brand-light data-[highlighted]:text-brand data-[state=checked]:font-semibold"
          >
            <span>{{ option.label }}</span>
            <SelectItemIndicator class="absolute right-2 inline-flex items-center">
              <Check class="size-4 text-brand" />
            </SelectItemIndicator>
          </SelectItem>
        </SelectViewport>
      </SelectContent>
    </SelectPortal>
  </SelectRoot>
</template>
