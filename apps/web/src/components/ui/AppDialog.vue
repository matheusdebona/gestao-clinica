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
    title: string
    description?: string
  }>(),
  {
    description: '',
  },
)
</script>

<template>
  <DialogRoot v-model:open="open">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 z-40 bg-title/40 data-[state=open]:animate-in" />
      <DialogContent
        class="fixed top-1/2 left-1/2 z-50 w-[min(100%-2rem,420px)] -translate-x-1/2 -translate-y-1/2 rounded-card bg-surface p-6 shadow-floating outline-none"
      >
        <DialogTitle class="text-lg font-semibold text-title">{{ title }}</DialogTitle>
        <DialogDescription v-if="description" class="mt-2 text-sm text-muted">
          {{ description }}
        </DialogDescription>
        <div class="mt-4 text-sm text-body">
          <slot />
        </div>
        <div class="mt-6 flex flex-wrap justify-end gap-2">
          <slot name="footer">
            <DialogClose as-child>
              <Button variant="secondary">Fechar</Button>
            </DialogClose>
          </slot>
        </div>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>
