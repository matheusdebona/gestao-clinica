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
      <DialogOverlay class="sv-scrim fixed inset-0 z-40" />
      <DialogContent
        class="glass-regular fixed inset-x-3 bottom-3 z-50 max-h-[min(85dvh,720px)] w-auto overflow-y-auto rounded-chrome p-5 outline-none md:inset-x-auto md:top-3 md:right-3 md:bottom-3 md:w-[min(100%-1.5rem,380px)]"
      >
        <DialogTitle class="text-[17px] font-semibold tracking-[-0.02em] text-title">
          {{ title }}
        </DialogTitle>
        <DialogDescription v-if="description" class="mt-1.5 text-[13px] text-muted">
          {{ description }}
        </DialogDescription>
        <div class="mt-4 text-[15px] text-body">
          <slot />
        </div>
        <div class="mt-5 flex justify-end gap-2">
          <slot name="footer">
            <DialogClose as-child>
              <Button variant="secondary">OK</Button>
            </DialogClose>
          </slot>
        </div>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>
