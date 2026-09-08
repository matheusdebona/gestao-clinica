<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import ListCard from '@/components/ui/ListCard.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { PAYMENT_CATALOG_PERMISSIONS, paymentCatalogHome } from '@/features/payments/access'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const shortcuts = computed(() => {
  const items: { title: string; meta: string; to: { name: string } }[] = []
  if (auth.can('sales.create')) {
    items.push({
      title: 'Nova venda',
      meta: 'Paciente, itens, pagamentos e confirmação',
      to: { name: 'sales-new' },
    })
  }
  if (auth.can('appointments.view')) {
    items.push({
      title: 'Agenda',
      meta: 'Sessões, retornos e o dia da clínica',
      to: { name: 'appointments' },
    })
  }
  if (auth.can('clients.view')) {
    items.push({
      title: 'Pacientes',
      meta: 'Cadastro, WhatsApp e origem',
      to: { name: 'clients' },
    })
  }
  if (auth.can('treatments.view')) {
    items.push({
      title: 'Tratamentos',
      meta: 'Casos clínicos e consumo de sessão',
      to: { name: 'treatments' },
    })
  }
  return items
})

function openMetrics() {
  void router.push({ name: 'metrics' })
}

function openAlerts() {
  void router.push({ name: 'notifications' })
}

function openPayments() {
  void router.push({ name: paymentCatalogHome((name) => auth.can(name)) })
}

function openShortcut(to: { name: string }) {
  void router.push(to)
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
    <PermissionGate :permission="[...PAYMENT_CATALOG_PERMISSIONS]" mode="any">
      <SurfaceCard :padding="false">
        <div class="px-5 py-2">
          <ListCard
            title="Métodos de pagamento"
            meta="Dinheiro, PIX, cartão, bandeiras e operadoras"
            @action="openPayments"
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
    <SurfaceCard v-if="shortcuts.length" :padding="false">
      <div class="divide-y divide-border-divider px-5 py-2">
        <ListCard
          v-for="item in shortcuts"
          :key="item.title"
          :title="item.title"
          :meta="item.meta"
          @action="openShortcut(item.to)"
        />
      </div>
    </SurfaceCard>
  </div>
</template>
