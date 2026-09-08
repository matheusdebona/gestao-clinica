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
import { deactivateCardOperator, getCardOperator, updateCardOperator } from '@/features/payments/api'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'

const props = defineProps<{
  operatorId: number
}>()

const router = useRouter()
const toast = useToastStore()
const queryClient = useQueryClient()
const confirmOpen = ref(false)
const idRef = computed(() => props.operatorId)

const { data: operator, isPending, isError } = useQuery({
  queryKey: ['card-operators', idRef],
  queryFn: () => getCardOperator(idRef.value),
})

const deactivateMutation = useMutation({
  mutationFn: () => deactivateCardOperator(props.operatorId),
  onSuccess: async () => {
    toast.success('Operadora desativada')
    await queryClient.invalidateQueries({ queryKey: ['card-operators'] })
    await router.push({ name: 'card-operators' })
  },
  onError: (error) => {
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível desativar.')
  },
})

const { mutate: reactivate, isPending: reactivating } = useMutation({
  mutationFn: () => updateCardOperator(props.operatorId, { is_active: true }),
  onSuccess: async () => {
    toast.success('Operadora reativada')
    await queryClient.invalidateQueries({ queryKey: ['card-operators'] })
  },
})
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="operator?.name ?? 'Operadora'">
      <template #actions>
        <Button variant="ghost" @click="router.push({ name: 'card-operators' })">Voltar</Button>
        <PermissionGate v-if="operator?.is_active" permission="card_operators.manage">
          <Button
            variant="secondary"
            @click="router.push({ name: 'card-operators-edit', params: { id: String(props.operatorId) } })"
          >
            Editar
          </Button>
        </PermissionGate>
        <PermissionGate v-if="operator?.is_active" permission="card_operators.manage">
          <Button variant="destructive" @click="confirmOpen = true">Desativar</Button>
        </PermissionGate>
        <PermissionGate v-else-if="operator" permission="card_operators.manage">
          <Button variant="secondary" :loading="reactivating" @click="reactivate()">Reativar</Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="operator && !operator.is_active" variant="warning" title="Inativa">
      Esta operadora está desativada. Pagamentos já registrados continuam com o vínculo.
    </Banner>
    <Banner v-if="isError" variant="danger" title="Não encontrado">Operadora indisponível.</Banner>
    <SurfaceCard v-else-if="isPending">
      <Skeleton class="h-6 w-40" />
    </SurfaceCard>
    <SurfaceCard v-else-if="operator">
      <dl class="flex flex-col gap-4">
        <div>
          <dt class="text-[13px] text-muted">Nome</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ operator.name }}</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Código</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ operator.code || '—' }}</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Antecipação automática</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ operator.auto_anticipate ? 'Sim' : 'Não' }}</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Situação</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ operator.is_active ? 'Ativa' : 'Inativa' }}</dd>
        </div>
      </dl>
    </SurfaceCard>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Desativar esta operadora?"
      description="Pagamentos já registrados não perdem o vínculo. A operadora some das listas ativas."
      confirm-label="Desativar"
      @confirm="deactivateMutation.mutate()"
    />
  </div>
</template>
