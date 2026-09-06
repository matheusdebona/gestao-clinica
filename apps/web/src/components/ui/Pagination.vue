<script setup lang="ts">
import { computed } from 'vue'
import Button from './Button.vue'

const props = withDefaults(
  defineProps<{
    page: number
    lastPage: number
    disabled?: boolean
  }>(),
  {
    disabled: false,
  },
)

const emit = defineEmits<{
  'update:page': [page: number]
}>()

const canPrev = computed(() => props.page > 1)
const canNext = computed(() => props.page < props.lastPage)
</script>

<template>
  <nav class="flex items-center justify-between gap-3" aria-label="Paginação">
    <Button
      variant="ghost"
      :disabled="disabled || !canPrev"
      @click="emit('update:page', page - 1)"
    >
      Anterior
    </Button>
    <p class="text-[13px] text-muted">{{ page }} / {{ lastPage }}</p>
    <Button
      variant="ghost"
      :disabled="disabled || !canNext"
      @click="emit('update:page', page + 1)"
    >
      Próxima
    </Button>
  </nav>
</template>
