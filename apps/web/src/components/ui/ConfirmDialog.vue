<script setup lang="ts">
import {
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
} from 'reka-ui'
import Button from './Button.vue'

const open = defineModel<boolean>('open', { default: false })

withDefaults(
  defineProps<{
    title?: string
    description?: string
    confirmLabel?: string
    cancelLabel?: string
  }>(),
  {
    title: 'Confirmar ação',
    description: 'Esta ação não pode ser desfeita.',
    confirmLabel: 'Confirmar',
    cancelLabel: 'Cancelar',
  },
)

const emit = defineEmits<{
  confirm: []
}>()
</script>

<template>
  <DialogRoot v-model:open="open">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 z-40 bg-title/40" />
      <DialogContent
        class="fixed top-1/2 left-1/2 z-50 w-[min(100%-2rem,400px)] -translate-x-1/2 -translate-y-1/2 rounded-card bg-surface p-6 shadow-floating outline-none"
      >
        <DialogTitle class="text-lg font-semibold text-title">{{ title }}</DialogTitle>
        <DialogDescription class="mt-2 text-sm text-muted">
          {{ description }}
        </DialogDescription>
        <div class="mt-6 flex flex-wrap justify-end gap-2">
          <DialogClose as-child>
            <Button variant="ghost">{{ cancelLabel }}</Button>
          </DialogClose>
          <Button
            variant="destructive"
            @click="
              () => {
                emit('confirm')
                open = false
              }
            "
          >
            {{ confirmLabel }}
          </Button>
        </div>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>
