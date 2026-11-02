<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed } from 'vue'
import Banner from '@/components/ui/Banner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { useAuthStore } from '@/stores/auth'
import ClaudeIntegrationCard from './ClaudeIntegrationCard.vue'
import { INTEGRATIONS_KEY, listIntegrations } from './api'

const auth = useAuthStore()
const canManage = computed(() => auth.can('integrations.manage'))

const { data, isPending, isError } = useQuery({
  queryKey: INTEGRATIONS_KEY,
  queryFn: listIntegrations,
  enabled: canManage,
})

const claude = computed(() => data.value?.find((item) => item.key === 'claude_mcp') ?? null)

/** Próximas integrações do catálogo (só vitrine, sem ação). */
const upcoming = [
  { name: 'Google Agenda', description: 'Sincronizar consultas com a agenda dos profissionais.' },
  { name: 'WhatsApp Business', description: 'Enviar confirmações e lembretes direto do HOF Pay.' },
]
</script>

<template>
  <div class="mx-auto flex w-full max-w-[880px] flex-col gap-6">
    <PageHeader
      title="Integrações"
      description="Conecte o HOF Pay a outros serviços."
      hint="Cada integração mostra o que ela consegue ver. Só administradores da clínica conectam e revogam."
    />

    <Banner v-if="!canManage" variant="danger" title="Sem permissão">
      Só administradores da clínica conectam e revogam integrações.
    </Banner>

    <Banner v-else-if="isError" variant="danger" title="Não foi possível carregar">
      Tente de novo em instantes.
    </Banner>

    <SurfaceCard v-else-if="isPending">
      <Skeleton class="h-6 w-40" />
      <Skeleton class="mt-3 h-10 w-full" />
      <Skeleton class="mt-3 h-24 w-full" />
    </SurfaceCard>

    <ClaudeIntegrationCard v-else-if="claude" :integration="claude" />

    <div v-if="canManage" class="grid gap-3 sm:grid-cols-2">
      <SurfaceCard v-for="item in upcoming" :key="item.name" class="opacity-70">
        <div class="flex items-center justify-between gap-2">
          <h2 class="text-[15px] font-semibold text-title">{{ item.name }}</h2>
          <span class="text-[12px] text-muted">Em breve</span>
        </div>
        <p class="mt-1 text-[13px] text-muted">{{ item.description }}</p>
      </SurfaceCard>
    </div>
  </div>
</template>
