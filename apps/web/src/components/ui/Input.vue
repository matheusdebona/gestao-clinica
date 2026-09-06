<script setup lang="ts">
import { cn } from '@/lib/cn'
import { computed, useAttrs } from 'vue'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    modelValue?: string | number
    type?: 'text' | 'number' | 'password' | 'email' | 'date' | 'datetime-local' | 'search' | 'tel'
    invalid?: boolean
    disabled?: boolean
    placeholder?: string
    id?: string
  }>(),
  {
    modelValue: '',
    type: 'text',
    invalid: false,
    disabled: false,
    placeholder: '',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const attrs = useAttrs()

const classes = computed(() =>
  cn(
    'h-11 w-full rounded-[10px] border bg-input px-3.5 text-[15px] text-title transition-colors placeholder:text-muted disabled:cursor-not-allowed disabled:opacity-50',
    props.invalid
      ? 'border-danger/50 bg-danger-light'
      : 'border-transparent focus-visible:border-brand/40 focus-visible:bg-surface',
  ),
)

function onInput(event: Event) {
  emit('update:modelValue', (event.target as HTMLInputElement).value)
}
</script>

<template>
  <input
    :id="id"
    :type="type"
    :class="classes"
    :value="modelValue"
    :placeholder="placeholder"
    :disabled="disabled"
    :aria-invalid="invalid || undefined"
    v-bind="attrs"
    @input="onInput"
  />
</template>
