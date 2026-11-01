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
import ActionBar from './ActionBar.vue'
import Button from './Button.vue'

const open = defineModel<boolean>('open', { default: false })

const props = withDefaults(
  defineProps<{
    title: string
    description?: string
    /** Kept for compatibility: footers always stack full width on mobile. */
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
        class="glass-regular fixed inset-x-3 bottom-3 z-50 flex max-h-[min(85dvh,720px)] w-auto flex-col rounded-chrome outline-none md:inset-x-auto md:top-3 md:right-3 md:bottom-3 md:max-h-none md:w-[min(100%-1.5rem,380px)]"
      >
        <div class="shrink-0 px-5 pt-5">
          <DialogTitle class="text-[17px] font-semibold tracking-[-0.02em] text-title">
            {{ title }}
          </DialogTitle>
          <DialogDescription v-if="description" class="mt-1.5 text-[13px] text-muted">
            {{ description }}
          </DialogDescription>
        </div>
        <div
          class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 pt-4 pb-1 text-[15px] text-body [-webkit-overflow-scrolling:touch]"
        >
          <slot />
        </div>
        <ActionBar class="shrink-0 px-5 pt-4 pb-[max(1.25rem,env(safe-area-inset-bottom))]">
          <template v-if="$slots['footer-negative']" #negative>
            <slot name="footer-negative" />
          </template>
          <slot name="footer">
            <DialogClose as-child>
              <Button variant="secondary">OK</Button>
            </DialogClose>
          </slot>
        </ActionBar>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>
