<script setup lang="ts">
import { cn } from '@/lib/cn'
import { Check, ChevronDown } from '@lucide/vue'
import {
  SelectContent,
  SelectItem,
  SelectItemIndicator,
  SelectItemText,
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

/** Reka UI forbids empty SelectItem values; filters use `value: ''` for "all". */
const EMPTY_OPTION_VALUE = '__empty__'

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
    placeholder: 'Selecionar',
    invalid: false,
    disabled: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const selectedLabel = computed(
  () => props.options.find((option) => option.value === props.modelValue)?.label ?? '',
)

const rootValue = computed(() => {
  if (props.modelValue !== '') {
    return props.modelValue
  }
  return props.options.some((option) => option.value === '') ? EMPTY_OPTION_VALUE : undefined
})

function itemValue(option: SelectOption): string {
  return option.value === '' ? EMPTY_OPTION_VALUE : option.value
}

function onRootUpdate(value: unknown) {
  const next = value == null ? '' : String(value)
  emit('update:modelValue', next === EMPTY_OPTION_VALUE ? '' : next)
}

const triggerClass = computed(() =>
  cn(
    'glass-field inline-flex h-11 w-full items-center justify-between gap-2 rounded-[12px] px-3.5 text-[15px] text-title outline-none disabled:cursor-not-allowed disabled:opacity-50',
  ),
)
</script>

<template>
  <SelectRoot
    :model-value="rootValue"
    :disabled="disabled"
    @update:model-value="onRootUpdate"
  >
    <SelectTrigger :id="id" :class="triggerClass" :aria-invalid="invalid || undefined">
      <SelectValue :placeholder="placeholder" class="truncate text-left">
        <span :class="selectedLabel ? 'text-title' : 'text-muted'">{{ selectedLabel || placeholder }}</span>
      </SelectValue>
      <ChevronDown class="size-4 shrink-0 text-muted" :stroke-width="1.75" aria-hidden="true" />
    </SelectTrigger>
    <SelectPortal>
      <SelectContent
        class="glass-menu z-50 min-w-[var(--reka-select-trigger-width)] overflow-hidden rounded-[14px]"
        :side-offset="8"
        position="popper"
      >
        <SelectViewport class="p-1">
          <SelectItem
            v-for="option in options"
            :key="itemValue(option)"
            :value="itemValue(option)"
            class="relative flex cursor-pointer select-none items-center rounded-[10px] py-2.5 pr-8 pl-3 text-[15px] text-body outline-none data-[highlighted]:bg-brand-light/60 data-[state=checked]:text-title"
          >
            <SelectItemText>{{ option.label }}</SelectItemText>
            <SelectItemIndicator class="absolute right-2 inline-flex items-center">
              <Check class="size-4 text-brand" :stroke-width="2" />
            </SelectItemIndicator>
          </SelectItem>
        </SelectViewport>
      </SelectContent>
    </SelectPortal>
  </SelectRoot>
</template>
