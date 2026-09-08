<script setup lang="ts">
import { computed, useAttrs } from 'vue'
import Input from '@/components/ui/Input.vue'
import { formatPhoneBR, omitValueListeners, phoneDigits } from '@/lib/masks'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    modelValue?: string | number
    invalid?: boolean
    disabled?: boolean
    placeholder?: string
    id?: string
    autocomplete?: string
  }>(),
  {
    modelValue: '',
    invalid: false,
    disabled: false,
    placeholder: '(11) 99999-9999',
    autocomplete: 'tel',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const attrs = useAttrs()
const forwarded = computed(() => omitValueListeners({ ...(attrs as Record<string, unknown>) }))
const display = computed(() => formatPhoneBR(props.modelValue))

function onTyped(raw: string) {
  emit('update:modelValue', phoneDigits(raw))
}
</script>

<template>
  <Input
    v-bind="forwarded"
    :id="id"
    type="tel"
    inputmode="numeric"
    :autocomplete="autocomplete"
    :model-value="display"
    :invalid="invalid"
    :disabled="disabled"
    :placeholder="placeholder"
    @update:model-value="onTyped"
  />
</template>
