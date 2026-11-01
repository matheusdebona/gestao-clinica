<script setup lang="ts">
import InfoTip from './InfoTip.vue'

/** Card/section heading with the helper text behind an InfoTip (default pattern for long hints). */
withDefaults(
  defineProps<{
    title: string
    /** Long explanation, shown in the "i" popover. */
    hint?: string
    /** Optional short one-line subtitle kept inline. */
    description?: string
    as?: 'h2' | 'h3'
  }>(),
  { hint: '', description: '', as: 'h2' },
)
</script>

<template>
  <div class="min-w-0">
    <div class="flex items-center gap-1.5">
      <component :is="as">{{ title }}</component>
      <InfoTip v-if="hint" :text="hint" :label="`Sobre: ${title}`" />
    </div>
    <p v-if="description" class="mt-1 text-[13px] text-muted">{{ description }}</p>
  </div>
</template>
