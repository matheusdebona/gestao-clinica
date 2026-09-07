<script setup lang="ts">
import { computed, useAttrs } from 'vue'
import Input from '@/components/ui/Input.vue'
import { decimalFromMoneyInput, formatMoneyAmount, omitValueListeners } from '@/lib/masks'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    modelValue?: string | number
    invalid?: boolean
    disabled?: boolean
    placeholder?: string
    id?: string
  }>(),
  {
    modelValue: '',
    invalid: false,
    disabled: false,
    placeholder: '0,00',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const attrs = useAttrs()
const forwarded = computed(() => omitValueListeners(attrs as Record<string, unknown>))
const display = computed(() => formatMoneyAmount(props.modelValue))

function onTyped(raw: string) {
  emit('update:modelValue', decimalFromMoneyInput(raw))
}
</script>

<template>
  <div class="relative w-full">
    <span
      class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-[15px] text-muted"
    >
      R$
    </span>
    <Input
      v-bind="forwarded"
      :id="id"
      class="pl-11"
      type="text"
      inputmode="numeric"
      autocomplete="off"
      :model-value="display"
      :invalid="invalid"
      :disabled="disabled"
      :placeholder="placeholder"
      @update:model-value="onTyped"
    />
  </div>
</template>
