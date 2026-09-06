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

const props = withDefaults(
  defineProps<{
    title?: string
    description?: string
    confirmLabel?: string
    cancelLabel?: string
    confirmVariant?: 'destructive' | 'primary'
  }>(),
  {
    title: 'Confirmar',
    description: 'Esta ação não pode ser desfeita.',
    confirmLabel: 'Confirmar',
    cancelLabel: 'Cancelar',
    confirmVariant: 'destructive',
  },
)

const emit = defineEmits<{
  confirm: []
}>()
</script>

<template>
  <DialogRoot v-model:open="open">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 z-40 bg-black/25 backdrop-blur-[2px]" />
      <DialogContent
        class="fixed top-1/2 left-1/2 z-50 w-[min(100%-2rem,360px)] -translate-x-1/2 -translate-y-1/2 rounded-[14px] bg-surface p-5 outline-none"
      >
        <DialogTitle class="text-[17px] font-semibold tracking-[-0.02em] text-title">
          {{ title }}
        </DialogTitle>
        <DialogDescription class="mt-1.5 text-[13px] text-muted">
          {{ description }}
        </DialogDescription>
        <div class="mt-5 flex justify-end gap-2">
          <DialogClose as-child>
            <Button variant="ghost">{{ cancelLabel }}</Button>
          </DialogClose>
          <Button
            :variant="props.confirmVariant"
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
