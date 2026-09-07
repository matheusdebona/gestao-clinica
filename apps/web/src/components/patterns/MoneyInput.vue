<script setup lang="ts">
import Input from '@/components/ui/Input.vue'

const model = defineModel<string>({ default: '' })

withDefaults(
  defineProps<{
    id?: string
    invalid?: boolean
    disabled?: boolean
    placeholder?: string
  }>(),
  {
    invalid: false,
    disabled: false,
    placeholder: '0,00',
  },
)
</script>

<template>
  <div class="relative w-full">
    <span
      class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-[15px] text-muted"
    >
      R$
    </span>
    <Input
      :id="id"
      v-model="model"
      type="text"
      inputmode="decimal"
      autocomplete="off"
      :invalid="invalid"
      :disabled="disabled"
      :placeholder="placeholder"
      class="pl-11"
    />
  </div>
</template>
