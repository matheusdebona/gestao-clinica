<script setup lang="ts">
import { cn } from '@/lib/cn'
import { computed, useAttrs } from 'vue'

defineOptions({ inheritAttrs: false })

withDefaults(
  defineProps<{
    modelValue?: string
    invalid?: boolean
    disabled?: boolean
    placeholder?: string
    id?: string
    rows?: number
  }>(),
  {
    modelValue: '',
    invalid: false,
    disabled: false,
    placeholder: '',
    rows: 3,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const attrs = useAttrs()

const classes = computed(() =>
  cn(
    'glass-field w-full resize-y rounded-[12px] px-3.5 py-2.5 text-[15px] text-title placeholder:text-muted disabled:cursor-not-allowed disabled:opacity-50',
    typeof attrs.class === 'string' ? attrs.class : undefined,
  ),
)

function onInput(event: Event) {
  emit('update:modelValue', (event.target as HTMLTextAreaElement).value)
}
</script>

<template>
  <textarea
    :id="id"
    :class="classes"
    :value="modelValue"
    :placeholder="placeholder"
    :disabled="disabled"
    :rows="rows"
    :aria-invalid="invalid || undefined"
    v-bind="{ ...attrs, class: undefined }"
    @input="onInput"
  />
</template>
