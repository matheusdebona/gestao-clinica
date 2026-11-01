<script setup lang="ts">
import { cn } from '@/lib/cn'
import { Check, ChevronDown, Search } from '@lucide/vue'
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
import { computed, ref } from 'vue'

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
    /** Show a filter box when there are at least this many options (0 disables). */
    filterThreshold?: number
  }>(),
  {
    modelValue: '',
    placeholder: 'Selecionar',
    invalid: false,
    disabled: false,
    filterThreshold: 10,
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

/** iOS notch / home-indicator insets, measured once (0 elsewhere). */
function safeInset(side: 'top' | 'bottom'): number {
  if (typeof document === 'undefined') return 0
  const probe = document.createElement('div')
  probe.style.cssText = `position:fixed;visibility:hidden;padding-${side}:env(safe-area-inset-${side})`
  document.body.appendChild(probe)
  const value = parseFloat(getComputedStyle(probe).getPropertyValue(`padding-${side}`)) || 0
  probe.remove()
  return value
}
const collisionPadding = {
  top: 12 + safeInset('top'),
  bottom: 16 + safeInset('bottom'),
  left: 12,
  right: 12,
}

const open = ref(false)
const query = ref('')
const showFilter = computed(
  () => props.filterThreshold > 0 && props.options.length >= props.filterThreshold,
)

function normalize(text: string): string {
  return text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
}

const visibleOptions = computed(() => {
  const q = normalize(query.value.trim())
  if (!q) return props.options
  // The trigger renders the selected label itself, so filtering it out is safe.
  return props.options.filter((option) => normalize(option.label).includes(q))
})

function onOpenChange(value: boolean) {
  open.value = value
  if (!value) query.value = ''
}

const triggerClass = computed(() =>
  cn(
    'glass-field sv-field-control inline-flex h-11 w-full items-center justify-between gap-2 rounded-[12px] px-3.5 text-title outline-none disabled:cursor-not-allowed disabled:opacity-50',
  ),
)
</script>

<template>
  <SelectRoot
    :model-value="rootValue"
    :disabled="disabled"
    :open="open"
    @update:open="onOpenChange"
    @update:model-value="onRootUpdate"
  >
    <SelectTrigger :id="id" :class="triggerClass" :aria-invalid="invalid || undefined">
      <SelectValue :placeholder="placeholder" class="truncate text-left">
        <span :class="selectedLabel ? 'text-title' : 'text-muted'">{{ selectedLabel || placeholder }}</span>
      </SelectValue>
      <ChevronDown class="size-4 shrink-0 text-muted" :stroke-width="1.75" aria-hidden="true" />
    </SelectTrigger>
    <SelectPortal>
      <!--
        Bounded by the space Reka/Floating UI measures between the trigger and the viewport edge
        (dialogs included), flips above when there is more room there, and scrolls inside with
        touch. Safe-area insets keep it off the iOS home indicator / notch.
      -->
      <SelectContent
        class="glass-menu z-[60] flex max-h-[min(var(--reka-select-content-available-height,60dvh),420px)] min-w-[var(--reka-select-trigger-width)] flex-col overflow-hidden rounded-[14px]"
        :side-offset="8"
        position="popper"
        :avoid-collisions="true"
        :collision-padding="collisionPadding"
        sticky="always"
      >
        <div v-if="showFilter" class="shrink-0 border-b border-border-divider p-1.5">
          <label class="glass-field flex h-10 items-center gap-2 rounded-[10px] px-3">
            <Search class="size-4 shrink-0 text-muted" :stroke-width="1.75" aria-hidden="true" />
            <input
              v-model="query"
              type="search"
              inputmode="search"
              autocomplete="off"
              placeholder="Filtrar"
              aria-label="Filtrar opções"
              class="w-full min-w-0 bg-transparent text-[16px] text-title outline-none placeholder:text-muted"
              @keydown.stop
              @pointerdown.stop
            />
          </label>
        </div>
        <SelectViewport
          class="min-h-0 flex-1 touch-pan-y overflow-y-auto overscroll-contain p-1 [-webkit-overflow-scrolling:touch]"
        >
          <SelectItem
            v-for="option in visibleOptions"
            :key="itemValue(option)"
            :value="itemValue(option)"
            class="relative flex cursor-pointer select-none items-center rounded-[10px] py-2.5 pr-8 pl-3 text-[15px] text-body outline-none data-[highlighted]:bg-brand-light/60 data-[state=checked]:text-title"
          >
            <SelectItemText>{{ option.label }}</SelectItemText>
            <SelectItemIndicator class="absolute right-2 inline-flex items-center">
              <Check class="size-4 text-brand" :stroke-width="2" />
            </SelectItemIndicator>
          </SelectItem>
          <p
            v-if="showFilter && visibleOptions.length === 0"
            class="px-3 py-2.5 text-[15px] text-muted"
          >
            Nada encontrado.
          </p>
        </SelectViewport>
      </SelectContent>
    </SelectPortal>
  </SelectRoot>
</template>
