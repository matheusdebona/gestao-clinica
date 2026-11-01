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
  /** Any of these permissions shows the entry. */
  permissions: string[]
}

const entries: SettingsEntry[] = [
  {
    name: 'lead-statuses',
    title: 'Status de leads',
    description: 'Etapas do funil: nome, cor, ordem e status inicial.',
    permissions: ['lead_statuses.manage'],
  },
  {
    name: 'client-origins',
    title: 'Origens',
    description: 'De onde vêm leads e pacientes (Instagram, indicação…).',
    permissions: ['client_origins.manage'],
  },
  {
    name: 'campaigns',
    title: 'Campanhas',
    description: 'Campanhas ligadas a cada origem.',
    permissions: ['campaigns.manage'],
  },
  {
    name: 'settings-whatsapp',
    title: 'Mensagens do WhatsApp',
    description: 'Textos de confirmação de sessão, contato com leads, retornos e orçamentos.',
    permissions: ['clinics.branding', 'clinics.manage'],
  },
  {
    name: 'settings-follow-up-tiers',
    title: 'Alertas de acompanhamento',
    description: 'Prazos sem contato (ex.: 3, 7, 30 e 60 dias) para avisar sobre leads parados.',
    permissions: ['clinics.branding', 'clinics.manage'],
  },
  {
    name: 'settings-budget-follow-up',
    title: 'Orçamentos esquecidos',
    description: 'Prazos sem resposta (ex.: 2, 5 e 10 dias) para cobrar orçamentos enviados.',
    permissions: ['clinics.branding', 'clinics.manage'],
  },
  {
    name: 'settings-card-fees',
    title: 'Taxas de cartão',
    description: 'Taxa da maquininha por operadora, bandeira e parcelas, e antecipação.',
    permissions: ['finance.manage'],
  },
  {
    name: 'settings-taxes',
    title: 'Impostos',
    description: 'Regime tributário e alíquota sobre a receita por mês de competência.',
    permissions: ['finance.view'],
  },
]

const visible = computed(() => entries.filter((entry) => entry.permissions.some((name) => auth.can(name))))
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
