<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { deactivateCampaign, getCampaign, updateCampaign } from '@/features/clients/api'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'

const props = defineProps<{
  campaignId: number
}>()

const router = useRouter()
const toast = useToastStore()
const queryClient = useQueryClient()
const confirmOpen = ref(false)
const idRef = computed(() => props.campaignId)

const { data: campaign, isPending, isError } = useQuery({
  queryKey: ['campaigns', idRef],
  queryFn: () => getCampaign(idRef.value),
})

const deactivateMutation = useMutation({
  mutationFn: () => deactivateCampaign(props.campaignId),
  onSuccess: async () => {
    toast.success('Campanha desativada')
    await queryClient.invalidateQueries({ queryKey: ['campaigns'] })
    await router.push({ name: 'campaigns' })
  },
  onError: (error) => {
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível desativar.')
  },
})

const { mutate: reactivate, isPending: reactivating } = useMutation({
  mutationFn: () => updateCampaign(props.campaignId, { is_active: true }),
  onSuccess: async () => {
    toast.success('Campanha reativada')
    await queryClient.invalidateQueries({ queryKey: ['campaigns'] })
  },
})
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="campaign?.name ?? 'Campanha'">
      <template #actions>
        <Button variant="ghost" @click="router.push({ name: 'campaigns' })">Voltar</Button>
        <PermissionGate v-if="campaign?.is_active" permission="campaigns.manage">
          <Button
            variant="secondary"
            @click="router.push({ name: 'campaigns-edit', params: { id: String(props.campaignId) } })"
          >
            Editar
          </Button>
        </PermissionGate>
        <PermissionGate v-if="campaign?.is_active" permission="campaigns.manage">
          <Button variant="destructive" @click="confirmOpen = true">Desativar</Button>
        </PermissionGate>
        <PermissionGate v-else-if="campaign" permission="campaigns.manage">
          <Button variant="secondary" :loading="reactivating" @click="reactivate()">Reativar</Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="campaign && !campaign.is_active" variant="warning" title="Inativa">
      Esta campanha está desativada. Clientes já atribuídos continuam com o vínculo.
    </Banner>
    <Banner v-if="isError" variant="danger" title="Não encontrado">Campanha indisponível.</Banner>
    <SurfaceCard v-else-if="isPending">
      <Skeleton class="h-6 w-40" />
    </SurfaceCard>
    <SurfaceCard v-else-if="campaign">
      <dl class="flex flex-col gap-4">
        <div>
          <dt class="text-[13px] text-muted">Nome</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ campaign.name }}</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Origem</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ campaign.client_origin?.name ?? '—' }}</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Situação</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ campaign.is_active ? 'Ativa' : 'Inativa' }}</dd>
        </div>
      </dl>
    </SurfaceCard>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Desativar esta campanha?"
      description="Clientes já atribuídos não perdem o vínculo. A campanha some das listas ativas."
      confirm-label="Desativar"
      @confirm="deactivateMutation.mutate()"
    />
  </div>
</template>
