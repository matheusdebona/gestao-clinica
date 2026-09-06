<script setup lang="ts">
import { useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import ListCard from '@/components/ui/ListCard.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

function openMetrics() {
  void router.push({ name: 'metrics' })
}

function openAlerts() {
  void router.push({ name: 'notifications' })
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader
      :title="auth.clinicName ? auth.clinicName : 'Início'"
      :description="auth.user ? `Olá, ${auth.user.name}.` : undefined"
    />
    <PermissionGate permission="products.view">
      <SurfaceCard :padding="false">
        <div class="px-5 py-2">
          <ListCard
            title="Alertas"
            meta="Estoque baixo e avisos da agenda"
            @action="openAlerts"
          />
        </div>
      </SurfaceCard>
    </PermissionGate>
    <PermissionGate permission="metrics.view">
      <SurfaceCard :padding="false">
        <div class="px-5 py-2">
          <ListCard
            title="Métricas"
            meta="Faturamento, conversão, margem e estoque"
            @action="openMetrics"
          />
        </div>
      </SurfaceCard>
    </PermissionGate>
    <SurfaceCard>
      <h2>Em construção</h2>
      <p class="mt-2 text-[15px] text-muted">
        O menu já segue o visual da aplicação. As áreas clínicas entram na próxima etapa.
      </p>
    </SurfaceCard>
  </div>
</template>
