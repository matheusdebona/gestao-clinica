<script setup lang="ts">
import { cn } from '@/lib/cn'

/**
 * The one placement rule for action buttons:
 * - desktop (sm+): negative actions (#negative: Cancelar, Voltar, Excluir, Recusar…) on the
 *   LEFT; progress actions (default slot: Salvar, Confirmar, Respondeu…) right-aligned in a row,
 *   primary last (rightmost);
 * - mobile: every button full width, stacked; primary on top, negatives at the bottom.
 *
 * Put the primary action LAST in the default slot.
 */
const props = withDefaults(
  defineProps<{
    /** Keep negatives glued to positives (header clusters) instead of pushing them to the far left. */
    compact?: boolean
  }>(),
  { compact: false },
)

const full = 'max-sm:[&>*]:w-full max-sm:[&_button]:w-full max-sm:[&_a]:w-full'
</script>

<template>
  <div
    :class="
      cn(
        'flex flex-col-reverse gap-2 sm:flex-row sm:items-center',
        props.compact ? 'sm:justify-end' : 'sm:justify-between',
      )
    "
  >
    <div
      v-if="$slots.negative"
      :class="cn('flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center', full)"
    >
      <slot name="negative" />
    </div>
    <div
      v-if="$slots.default"
      :class="
        cn(
          'flex flex-col-reverse gap-2 sm:ml-auto sm:flex-row sm:flex-wrap sm:items-center sm:justify-end',
          full,
        )
      "
    >
      <slot />
    </div>
  </div>
</template>
