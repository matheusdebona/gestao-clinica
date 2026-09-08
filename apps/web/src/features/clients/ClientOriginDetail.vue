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
import { deactivateClientOrigin, getClientOrigin, updateClientOrigin } from '@/features/clients/api'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'

const props = defineProps<{
  originId: number
}>()

const router = useRouter()
const toast = useToastStore()
const queryClient = useQueryClient()
const confirmOpen = ref(false)
const idRef = computed(() => props.originId)

const { data: origin, isPending, isError } = useQuery({
  queryKey: ['client-origins', idRef],
  queryFn: () => getClientOrigin(idRef.value),
})

const deactivateMutation = useMutation({
  mutationFn: () => deactivateClientOrigin(props.originId),
  onSuccess: async () => {
    toast.success('Origem desativada')
    await queryClient.invalidateQueries({ queryKey: ['client-origins'] })
    await queryClient.invalidateQueries({ queryKey: ['campaigns'] })
    await router.push({ name: 'client-origins' })
  },
  onError: (error) => {
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível desativar.')
  },
})

const { mutate: reactivate, isPending: reactivating } = useMutation({
  mutationFn: () => updateClientOrigin(props.originId, { is_active: true }),
  onSuccess: async () => {
    toast.success('Origem reativada')
    await queryClient.invalidateQueries({ queryKey: ['client-origins'] })
  },
})

function goCampaigns() {
  void router.push({
    name: 'campaigns',
    query: { client_origin_id: String(props.originId) },
  })
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="origin?.name ?? 'Origem'">
      <template #actions>
        <Button variant="ghost" @click="router.push({ name: 'client-origins' })">Voltar</Button>
        <PermissionGate permission="campaigns.manage">
          <Button variant="secondary" @click="goCampaigns">Campanhas</Button>
        </PermissionGate>
        <PermissionGate v-if="origin?.is_active" permission="client_origins.manage">
          <Button
            variant="secondary"
            @click="router.push({ name: 'client-origins-edit', params: { id: String(props.originId) } })"
          >
            Editar
          </Button>
        </PermissionGate>
        <PermissionGate v-if="origin?.is_active" permission="client_origins.manage">
          <Button variant="destructive" @click="confirmOpen = true">Desativar</Button>
        </PermissionGate>
        <PermissionGate v-else-if="origin" permission="client_origins.manage">
          <Button variant="secondary" :loading="reactivating" @click="reactivate()">Reativar</Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="origin && !origin.is_active" variant="warning" title="Inativa">
      Esta origem está desativada. Pacientes já atribuídos continuam com o vínculo.
    </Banner>
    <Banner v-if="isError" variant="danger" title="Não encontrado">Origem indisponível.</Banner>
    <SurfaceCard v-else-if="isPending">
      <Skeleton class="h-6 w-40" />
    </SurfaceCard>
    <SurfaceCard v-else-if="origin">
      <dl class="flex flex-col gap-4">
        <div>
          <dt class="text-[13px] text-muted">Nome</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ origin.name }}</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Situação</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ origin.is_active ? 'Ativa' : 'Inativa' }}</dd>
        </div>
      </dl>
    </SurfaceCard>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Desativar esta origem?"
      description="Pacientes já atribuídos não perdem o vínculo. A origem some das listas ativas."
      confirm-label="Desativar"
      @confirm="deactivateMutation.mutate()"
    />
  </div>
</template>
