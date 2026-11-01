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
    /**
     * Desktop width: sm ≈ 400px (confirmations), md ≈ 560px, lg ≈ 720px (forms; use
     * `grid md:grid-cols-2` in the body so fields pair up). Always full width minus margins on mobile.
     */
    size?: 'sm' | 'md' | 'lg'
  }>(),
  {
    description: '',
    stackFooter: true,
    size: 'sm',
  },
)

const WIDTH = { sm: 'md:w-[400px]', md: 'md:w-[560px]', lg: 'md:w-[720px]' } as const
</script>

<template>
  <DialogRoot v-model:open="open">
    <DialogPortal>
      <DialogOverlay class="sv-scrim fixed inset-0 z-40" />
      <!--
        Never cut: bounded by the dynamic viewport; title and actions stay put and only the body
        scrolls when the content really is taller than the screen.
      -->
      <DialogContent
        :class="[
          'glass-regular fixed top-1/2 left-1/2 z-50 flex max-h-[calc(100dvh-1.5rem)] w-[calc(100%-1.5rem)] max-w-[calc(100vw-1.5rem)] -translate-x-1/2 -translate-y-1/2 flex-col rounded-[18px] outline-none',
          WIDTH[props.size],
        ]"
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
        <ActionBar class="shrink-0 px-5 pt-4 pb-5">
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
