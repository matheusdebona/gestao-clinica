<script setup lang="ts">
import { Info } from '@lucide/vue'
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui'
import { ref, useId } from 'vue'

/**
 * Default pattern for helper text: a small "i" next to a label or title.
 * Desktop: opens on hover or keyboard focus. Mobile: opens on tap, closes on outside tap / Esc.
 * The text is always exposed to screen readers via aria-describedby, open or not.
 */
const props = withDefaults(
  defineProps<{
    text: string
    /** Accessible name of the trigger. */
    label?: string
    side?: 'top' | 'bottom' | 'left' | 'right'
  }>(),
  { label: 'Mais informações', side: 'top' },
)

const open = ref(false)
const id = useId()
let hoverTimer: ReturnType<typeof setTimeout> | undefined
const hovering = ref(false)

function onEnter(event: PointerEvent) {
  if (event.pointerType !== 'mouse') return
  clearTimeout(hoverTimer)
  hovering.value = true
  open.value = true
}
function onLeave(event: PointerEvent) {
  if (event.pointerType !== 'mouse') return
  clearTimeout(hoverTimer)
  hoverTimer = setTimeout(() => {
    hovering.value = false
    open.value = false
  }, 120)
}
/** Tap toggles; a click while hovering keeps it open instead of closing it. */
function onUpdateOpen(value: boolean) {
  if (!value && hovering.value) return
  open.value = value
}
function onFocus(event: FocusEvent) {
  if ((event.target as HTMLElement).matches(':focus-visible')) open.value = true
}
</script>

<template>
  <PopoverRoot :open="open" @update:open="onUpdateOpen">
    <PopoverTrigger
      type="button"
      :aria-label="props.label"
      :aria-describedby="`${id}-text`"
      class="relative inline-flex size-5 shrink-0 items-center justify-center rounded-full align-middle text-muted outline-none transition-colors before:absolute before:-inset-2.5 before:content-[''] hover:text-brand focus-visible:text-brand focus-visible:ring-2 focus-visible:ring-brand/40"
      @pointerenter="onEnter"
      @pointerleave="onLeave"
      @focus="onFocus"
      @blur="hovering || (open = false)"
      @keydown.esc="open = false"
    >
      <Info class="size-4" :stroke-width="1.9" aria-hidden="true" />
    </PopoverTrigger>
    <span :id="`${id}-text`" class="sr-only">{{ props.text }}</span>
    <PopoverPortal>
      <PopoverContent
        :side="props.side"
        :side-offset="6"
        :collision-padding="12"
        :avoid-collisions="true"
        class="glass-menu z-[70] max-w-[min(320px,calc(100vw-24px))] rounded-[12px] px-3.5 py-2.5 text-[13px] leading-relaxed text-body outline-none"
        @pointerenter="onEnter"
        @pointerleave="onLeave"
        @open-auto-focus.prevent
        @close-auto-focus.prevent
      >
        <div role="tooltip"><slot>{{ props.text }}</slot></div>
      </PopoverContent>
    </PopoverPortal>
  </PopoverRoot>
</template>
