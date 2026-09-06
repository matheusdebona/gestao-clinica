<script setup lang="ts">
import { X } from '@lucide/vue'
import MoneyDisplay from '@/components/patterns/MoneyDisplay.vue'
import FormField from '@/components/ui/FormField.vue'
import IconButton from '@/components/ui/IconButton.vue'
import Input from '@/components/ui/Input.vue'
import MaskedBox from '@/components/ui/MaskedBox.vue'

const quantity = defineModel<string>('quantity', { required: true })

withDefaults(
  defineProps<{
    title: string
    unit?: string
    lineSale?: string | number | null
    invalid?: boolean
    readonly?: boolean
    quantityId?: string
  }>(),
  {
    unit: '',
    lineSale: null,
    invalid: false,
    readonly: false,
    quantityId: undefined,
  },
)

const emit = defineEmits<{
  remove: []
}>()
</script>

<template>
  <div class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
    <div class="min-w-0 flex-1">
      <p class="truncate text-[15px] font-medium text-title">{{ title }}</p>
      <p v-if="unit" class="mt-0.5 truncate text-[13px] text-muted">{{ unit }}</p>
      <p v-if="lineSale !== null && lineSale !== undefined && lineSale !== ''" class="mt-0.5">
        <MoneyDisplay :value="lineSale" />
      </p>
    </div>
    <div class="w-24 shrink-0">
      <FormField v-if="!readonly" label="Qtd" :html-for="quantityId">
        <template #default="{ invalid: fieldInvalid }">
          <Input
            :id="quantityId"
            v-model="quantity"
            type="text"
            inputmode="decimal"
            :invalid="invalid || fieldInvalid"
            aria-label="Quantidade"
          />
        </template>
      </FormField>
      <FormField v-else label="Qtd">
        <MaskedBox :value="quantity" />
      </FormField>
    </div>
    <div v-if="!readonly" class="mt-6">
      <IconButton label="Remover item" @click="emit('remove')">
        <X class="size-4" :stroke-width="1.75" />
      </IconButton>
    </div>
  </div>
</template>
