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

const triggerClass = computed(() =>
  cn(
    'inline-flex h-11 w-full items-center justify-between gap-2 rounded-[10px] border bg-input px-3.5 text-[15px] text-title outline-none transition-colors disabled:cursor-not-allowed disabled:opacity-50',
    props.invalid
      ? 'border-danger/50 bg-danger-light'
      : 'border-transparent data-[state=open]:border-brand/40 data-[state=open]:bg-surface',
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
      <ChevronDown class="size-4 shrink-0 text-muted" :stroke-width="1.75" aria-hidden="true" />
    </SelectTrigger>
    <SelectPortal>
      <SelectContent
        class="z-50 min-w-[var(--reka-select-trigger-width)] overflow-hidden rounded-[12px] border border-border-subtle bg-surface shadow-floating"
        :side-offset="6"
        position="popper"
      >
        <SelectViewport class="p-1">
          <SelectItem
            v-for="option in options"
            :key="option.value"
            :value="option.value"
            class="relative flex cursor-pointer select-none items-center rounded-[8px] py-2.5 pr-8 pl-3 text-[15px] text-body outline-none data-[highlighted]:bg-surface-muted data-[state=checked]:text-title"
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
