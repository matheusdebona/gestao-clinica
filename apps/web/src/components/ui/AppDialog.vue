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
    title: string
    description?: string
    /** Footer actions stacked full width below `sm` (first slot item ends up at the bottom). */
    stackFooter?: boolean
  }>(),
  {
    description: '',
    stackFooter: true,
  },
)
</script>

<template>
  <DialogRoot v-model:open="open">
    <DialogPortal>
      <DialogOverlay class="sv-scrim fixed inset-0 z-40" />
      <DialogContent
        class="glass-regular fixed top-1/2 left-1/2 z-50 w-[min(100%-2rem,400px)] -translate-x-1/2 -translate-y-1/2 rounded-[18px] p-5 outline-none"
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
        <div
          :class="
            props.stackFooter
              ? 'mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end'
              : 'mt-5 flex justify-end gap-2'
          "
        >
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
