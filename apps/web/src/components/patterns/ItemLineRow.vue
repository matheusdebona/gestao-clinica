<script setup lang="ts">
import { X } from '@lucide/vue'
import MoneyDisplay from '@/components/patterns/MoneyDisplay.vue'
import FormField from '@/components/ui/FormField.vue'
import IconButton from '@/components/ui/IconButton.vue'
import Input from '@/components/ui/Input.vue'
import MaskedBox from '@/components/ui/MaskedBox.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import { formatBRL } from '@/lib/formatters'

const quantity = defineModel<string>('quantity', { required: true })
const unitPrice = defineModel<string>('unitPrice')

withDefaults(
  defineProps<{
    title: string
    unit?: string
    lineSale?: string | number | null
    invalid?: boolean
    readonly?: boolean
    quantityId?: string
    priceId?: string
    showUnitPrice?: boolean
    priceInvalid?: boolean
  }>(),
  {
    unit: '',
    lineSale: null,
    invalid: false,
    readonly: false,
    quantityId: undefined,
    priceId: undefined,
    showUnitPrice: false,
    priceInvalid: false,
  },
)

const emit = defineEmits<{
  remove: []
}>()
</script>

<template>
  <div class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
    <div class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-start">
      <div class="min-w-0 flex-1">
        <p class="truncate text-[15px] font-medium text-title">{{ title }}</p>
        <p v-if="unit" class="mt-0.5 truncate text-[13px] text-muted">{{ unit }}</p>
        <p v-if="lineSale !== null && lineSale !== undefined && lineSale !== ''" class="mt-0.5">
          <MoneyDisplay :value="lineSale" />
        </p>
      </div>
      <div class="flex shrink-0 flex-wrap items-start gap-3">
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
        <div v-if="showUnitPrice" class="w-40 shrink-0">
          <FormField v-if="!readonly" label="Preço" :html-for="priceId">
            <template #default="{ invalid: fieldInvalid }">
              <MoneyInput
                :id="priceId"
                v-model="unitPrice"
                :invalid="priceInvalid || fieldInvalid"
                aria-label="Preço unitário"
              />
            </template>
          </FormField>
          <FormField v-else label="Preço">
            <MaskedBox :value="formatBRL(unitPrice)" />
          </FormField>
        </div>
      </div>
    </div>
    <div v-if="!readonly" class="shrink-0">
      <IconButton label="Remover item" @click="emit('remove')">
        <X class="size-4" :stroke-width="1.75" />
      </IconButton>
    </div>
  </div>
</template>
