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
import { deactivateClient, getClient, updateClient } from '@/features/clients/api'
import { formatBRL } from '@/lib/formatters'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'

const props = defineProps<{
  clientId: number
}>()

const router = useRouter()
const toast = useToastStore()
const queryClient = useQueryClient()
const confirmOpen = ref(false)

const idRef = computed(() => props.clientId)

const { data: client, isPending, isError } = useQuery({
  queryKey: ['clients', idRef],
  queryFn: () => getClient(idRef.value),
})

const deactivateMutation = useMutation({
  mutationFn: () => deactivateClient(props.clientId),
  onSuccess: async () => {
    toast.success('Cliente desativado')
    await queryClient.invalidateQueries({ queryKey: ['clients'] })
    await router.push({ name: 'clients' })
  },
  onError: (error) => {
    if (error instanceof ApiError && error.status === 403) {
      toast.error('Sem permissão para desativar.')
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível desativar.')
  },
})

const { mutate: reactivate, isPending: reactivating } = useMutation({
  mutationFn: () => updateClient(props.clientId, { is_active: true }),
  onSuccess: async () => {
    toast.success('Cliente reativado')
    await queryClient.invalidateQueries({ queryKey: ['clients'] })
  },
  onError: (error) => {
    if (error instanceof ApiError && error.status === 403) {
      toast.error('Sem permissão para atualizar.')
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível reativar.')
  },
})

function goEdit() {
  void router.push({ name: 'clients-edit', params: { id: String(props.clientId) } })
}

function goBack() {
  void router.push({ name: 'clients' })
}

function onConfirmDeactivate() {
  deactivateMutation.mutate()
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="client?.name ?? 'Cliente'">
      <template #actions>
        <Button variant="ghost" @click="goBack">Voltar</Button>
        <PermissionGate v-if="client?.is_active" permission="clients.update">
          <Button variant="secondary" @click="goEdit">Editar</Button>
        </PermissionGate>
        <PermissionGate v-if="client?.is_active" permission="clients.delete">
          <Button variant="destructive" @click="confirmOpen = true">Desativar</Button>
        </PermissionGate>
        <PermissionGate v-else-if="client" permission="clients.update">
          <Button
            variant="secondary"
            :loading="reactivating"
            @click="reactivate()"
          >
            Reativar
          </Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="client && !client.is_active" variant="warning" title="Inativo">
      Este cliente está desativado e não aparece na lista padrão.
    </Banner>

    <Banner v-if="isError" variant="danger" title="Não foi possível carregar">
      O cliente pode ter sido removido ou você não tem permissão.
    </Banner>

    <SurfaceCard v-else-if="isPending">
      <div class="flex flex-col gap-3">
        <Skeleton class="h-6 w-40" />
        <Skeleton class="h-5 w-56" />
        <Skeleton class="h-5 w-32" />
      </div>
    </SurfaceCard>

    <SurfaceCard v-else-if="client">
      <dl class="flex flex-col gap-4">
        <div>
          <dt class="text-[13px] text-muted">WhatsApp</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ client.whatsapp }}</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Origem</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ client.client_origin?.name ?? '—' }}</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Campanha</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ client.campaign?.name ?? '—' }}</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Valor da avaliação</dt>
          <dd class="mt-0.5 text-[15px] text-title">
            {{ formatBRL(client.initial_consultation_amount) }}
          </dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Duração</dt>
          <dd class="mt-0.5 text-[15px] text-title">
            {{
              client.service_duration_minutes
                ? `${client.service_duration_minutes} min`
                : '—'
            }}
          </dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Queixas principais</dt>
          <dd class="mt-0.5 whitespace-pre-wrap text-[15px] text-title">
            {{ client.main_pains || '—' }}
          </dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Notas</dt>
          <dd class="mt-0.5 whitespace-pre-wrap text-[15px] text-title">
            {{ client.notes || '—' }}
          </dd>
        </div>
      </dl>
    </SurfaceCard>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Desativar este cliente?"
      description="O cadastro permanece no histórico. Você pode reativar depois."
      confirm-label="Desativar"
      @confirm="onConfirmDeactivate"
    />
  </div>
</template>
