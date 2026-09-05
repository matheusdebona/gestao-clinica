<script setup lang="ts">
import { cn } from '@/lib/cn'
import { computed, useAttrs } from 'vue'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    modelValue?: string | number
    type?: 'text' | 'number' | 'password' | 'email' | 'date' | 'search' | 'tel'
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
    'w-full rounded-[10px] border bg-input px-3.5 py-2.5 text-sm text-title shadow-input transition-colors placeholder:text-muted disabled:cursor-not-allowed disabled:opacity-60',
    props.invalid
      ? 'border-danger focus-visible:outline-danger'
      : 'border-transparent focus-visible:border-brand',
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
