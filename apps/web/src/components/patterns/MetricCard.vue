<script setup lang="ts">
import SurfaceCard from '@/components/ui/SurfaceCard.vue'

withDefaults(
  defineProps<{
    label: string
    hint?: string
  }>(),
  {
    hint: '',
  },
)
</script>

<template>
  <SurfaceCard>
    <p class="text-[13px] font-medium text-muted">{{ label }}</p>
    <div class="mt-1 min-w-0 text-[22px] font-semibold tracking-[-0.03em] text-title tabular-nums">
      <slot />
    </div>
    <p v-if="hint" class="mt-1 text-[13px] text-muted">{{ hint }}</p>
  </SurfaceCard>
</template>
