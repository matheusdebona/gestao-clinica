<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

const props = defineProps<{
  permission: string | string[]
}>()

const auth = useAuthStore()

const allowed = computed(() => {
  const list = Array.isArray(props.permission) ? props.permission : [props.permission]
  return list.every((name) => auth.can(name))
})
</script>

<template>
  <slot v-if="allowed" />
</template>
