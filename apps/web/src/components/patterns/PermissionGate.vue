<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

const props = withDefaults(
  defineProps<{
    permission: string | string[]
    mode?: 'all' | 'any'
  }>(),
  {
    mode: 'all',
  },
)

const auth = useAuthStore()

const allowed = computed(() => {
  const list = Array.isArray(props.permission) ? props.permission : [props.permission]
  if (props.mode === 'any') {
    return list.some((name) => auth.can(name))
  }
  return list.every((name) => auth.can(name))
})
</script>

<template>
  <slot v-if="allowed" />
</template>
