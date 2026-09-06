<script setup lang="ts">
import { onUnmounted, watch } from 'vue'
import SearchField from '@/components/ui/SearchField.vue'

const model = defineModel<string>({ default: '' })

withDefaults(
  defineProps<{
    placeholder?: string
    disabled?: boolean
  }>(),
  {
    placeholder: 'Nome ou WhatsApp',
    disabled: false,
  },
)

const emit = defineEmits<{
  search: [value: string]
}>()

let timer: ReturnType<typeof setTimeout> | undefined

function emitSearch(value: string) {
  emit('search', value.trim())
}

watch(model, (value) => {
  window.clearTimeout(timer)
  timer = window.setTimeout(() => emitSearch(value), 300)
})

onUnmounted(() => {
  window.clearTimeout(timer)
})
</script>

<template>
  <SearchField
    v-model="model"
    :placeholder="placeholder"
    :disabled="disabled"
    @search="emitSearch"
  />
</template>
