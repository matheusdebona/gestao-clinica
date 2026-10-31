<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import Banner from '@/components/ui/Banner.vue'
import ListCard from '@/components/ui/ListCard.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

interface SettingsEntry {
  name: string
  title: string
  description: string
  permission: string
}

const entries: SettingsEntry[] = [
  {
    name: 'lead-statuses',
    title: 'Status de leads',
    description: 'Etapas do funil: nome, cor, ordem e status inicial.',
    permission: 'lead_statuses.manage',
  },
  {
    name: 'client-origins',
    title: 'Origens',
    description: 'De onde vêm leads e pacientes (Instagram, indicação…).',
    permission: 'client_origins.manage',
  },
  {
    name: 'campaigns',
    title: 'Campanhas',
    description: 'Campanhas ligadas a cada origem.',
    permission: 'campaigns.manage',
  },
]

const visible = computed(() => entries.filter((entry) => auth.can(entry.permission)))
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader title="Configurações" description="Cadastros usados no dia a dia da clínica." />

    <Banner v-if="visible.length === 0" variant="danger" title="Sem permissão">
      Você não pode alterar as configurações da clínica.
    </Banner>

    <SurfaceCard v-else :padding="false">
      <div class="divide-y divide-border-divider px-5 py-2">
        <ListCard
          v-for="entry in visible"
          :key="entry.name"
          :title="entry.title"
          :description="entry.description"
          @action="router.push({ name: entry.name })"
        />
      </div>
    </SurfaceCard>
  </div>
</template>
