<script setup lang="ts">
import { Search } from '@lucide/vue'
import Input from './Input.vue'
import IconButton from './IconButton.vue'

const model = defineModel<string>({ default: '' })

withDefaults(
  defineProps<{
    placeholder?: string
    disabled?: boolean
  }>(),
  {
    placeholder: 'Buscar…',
    disabled: false,
  },
)

const emit = defineEmits<{
  search: [value: string]
}>()
</script>

<template>
  <div class="relative flex w-full items-center">
    <Input
      v-model="model"
      type="search"
      :placeholder="placeholder"
      :disabled="disabled"
      class="!rounded-full !bg-surface-muted !pr-12 !shadow-none"
    />
    <div class="absolute right-1.5">
      <IconButton
        label="Buscar"
        :disabled="disabled"
        class="!bg-brand !text-inverse hover:!bg-brand-hover"
        @click="emit('search', model)"
      >
        <Search class="size-4" />
      </IconButton>
    </div>
  </div>
</template>
