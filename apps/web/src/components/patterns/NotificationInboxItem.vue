<script setup lang="ts">
import ListCard from '@/components/ui/ListCard.vue'

withDefaults(
  defineProps<{
    title: string
    message?: string
    relativeTime?: string
    unread?: boolean
    navigable?: boolean
  }>(),
  {
    message: '',
    relativeTime: '',
    unread: false,
    navigable: true,
  },
)

const emit = defineEmits<{
  action: []
}>()
</script>

<template>
  <ListCard
    :title="title"
    :description="message"
    :meta="relativeTime"
    :badge="unread ? 'Não lida' : ''"
    :badge-variant="unread ? 'warning' : 'muted'"
    :emphasis="unread"
    :show-chevron="navigable"
    @action="emit('action')"
  />
</template>
