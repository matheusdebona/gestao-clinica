<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '@/lib/cn'
import InfoTip from './InfoTip.vue'

/** Hints longer than this go behind an "i" next to the label instead of a paragraph. */
const INLINE_HINT_MAX = 60

const props = withDefaults(
  defineProps<{
    label: string
    hint?: string
    error?: string
    htmlFor?: string
    /** Force the hint inline even when long. */
    inlineHint?: boolean
  }>(),
  {
    hint: '',
    error: '',
    inlineHint: false,
  },
)

const tipHint = computed(
  () => Boolean(props.hint && props.label) && !props.inlineHint && props.hint.length > INLINE_HINT_MAX,
)
</script>

<template>
  <div :class="cn('flex w-full flex-col gap-1.5')">
    <div v-if="label" class="flex min-w-0 items-center gap-1.5">
      <label :for="htmlFor" class="text-[13px] font-medium text-title/70">
        {{ label }}
      </label>
      <InfoTip v-if="tipHint" :text="hint" :label="`Sobre: ${label}`" />
    </div>
    <slot :invalid="Boolean(error)" />
    <p v-if="error" class="text-[13px] text-danger" role="alert">
      {{ error }}
    </p>
    <p v-else-if="hint && !tipHint" class="text-[13px] text-muted">
      {{ hint }}
    </p>
  </div>
</template>
