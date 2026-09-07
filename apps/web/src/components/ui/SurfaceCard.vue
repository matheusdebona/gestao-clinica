<script setup lang="ts">
import { cn } from '@/lib/cn'
import GlassSurface from './GlassSurface.vue'

withDefaults(
  defineProps<{
    padding?: boolean
    class?: string
  }>(),
  {
    padding: true,
  },
)
</script>

<template>
  <GlassSurface
    as="section"
    material="regular"
    :padding="padding"
    :class="cn($props.class)"
  >
    <slot />
  </GlassSurface>
</template>
