<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import PageHeader from '@/components/ui/PageHeader.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'

const route = useRoute()

const title = computed(() => String(route.meta.title ?? 'Em breve'))
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="title" description="Esta área ainda não está disponível." />
    <SurfaceCard>
      <p class="text-[15px] text-muted">Em construção.</p>
    </SurfaceCard>
  </div>
</template>
