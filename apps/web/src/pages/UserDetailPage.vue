<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import UserDetail from '@/features/users/UserDetail.vue'

const route = useRoute()
const userId = computed(() => {
  const parsed = Number(route.params.id)
  return Number.isInteger(parsed) ? parsed : 0
})
</script>

<template>
  <UserDetail :user-id="userId" />
</template>
