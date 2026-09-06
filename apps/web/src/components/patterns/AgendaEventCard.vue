<script setup lang="ts">
import ListCard from '@/components/ui/ListCard.vue'

withDefaults(
  defineProps<{
    title: string
    meta: string
    badge: string
    badgeVariant?: 'success' | 'purple' | 'muted' | 'warning' | 'danger'
  }>(),
  {
    badgeVariant: 'muted',
  },
)

const emit = defineEmits<{
  action: []
}>()
</script>

<template>
  <ListCard
    :title="title"
    :meta="meta"
    :badge="badge"
    :badge-variant="badgeVariant"
    @action="emit('action')"
  />
</template>
