<script setup lang="ts">
import PageHeader from '@/components/ui/PageHeader.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader
      :title="auth.clinicName ? auth.clinicName : 'Início'"
      :description="auth.user ? `Olá, ${auth.user.name}.` : undefined"
    />
    <SurfaceCard>
      <h2>Em construção</h2>
      <p class="mt-2 text-[15px] text-muted">
        O menu já segue o visual da aplicação. As áreas clínicas entram na próxima etapa.
      </p>
    </SurfaceCard>
  </div>
</template>
