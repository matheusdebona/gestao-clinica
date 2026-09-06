<script setup lang="ts">
import { Search } from '@lucide/vue'

const model = defineModel<string>({ default: '' })

withDefaults(
  defineProps<{
    placeholder?: string
    disabled?: boolean
  }>(),
  {
    placeholder: 'Buscar',
    disabled: false,
  },
)

const emit = defineEmits<{
  search: [value: string]
}>()
</script>

<template>
  <div class="relative flex w-full items-center">
    <Search
      class="pointer-events-none absolute left-3 size-4 text-muted"
      :stroke-width="1.75"
      aria-hidden="true"
    />
    <input
      v-model="model"
      type="search"
      :placeholder="placeholder"
      :disabled="disabled"
      class="h-11 w-full rounded-[10px] border border-transparent bg-input pr-3.5 pl-10 text-[15px] text-title placeholder:text-muted disabled:opacity-50"
      @keydown.enter="emit('search', model)"
    />
  </div>
</template>
