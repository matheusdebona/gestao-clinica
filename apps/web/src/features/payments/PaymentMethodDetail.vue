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
import {
  deactivatePaymentMethod,
  getPaymentMethod,
  updatePaymentMethod,
} from '@/features/payments/api'
import { formatFeePercent, paymentKindLabel } from '@/features/payments/labels'
import { formatBRL } from '@/lib/formatters'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'

const props = defineProps<{
  methodId: number
}>()

const router = useRouter()
const toast = useToastStore()
const queryClient = useQueryClient()
const confirmOpen = ref(false)
const idRef = computed(() => props.methodId)

const { data: method, isPending, isError } = useQuery({
  queryKey: ['payment-methods', idRef],
  queryFn: () => getPaymentMethod(idRef.value),
})

const deactivateMutation = useMutation({
  mutationFn: () => deactivatePaymentMethod(props.methodId),
  onSuccess: async () => {
    toast.success('Método desativado')
    await queryClient.invalidateQueries({ queryKey: ['payment-methods'] })
    await router.push({ name: 'payment-methods' })
  },
  onError: (error) => {
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível desativar.')
  },
})

const { mutate: reactivate, isPending: reactivating } = useMutation({
  mutationFn: () => updatePaymentMethod(props.methodId, { is_active: true }),
  onSuccess: async () => {
    toast.success('Método reativado')
    await queryClient.invalidateQueries({ queryKey: ['payment-methods'] })
  },
})
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="method?.name ?? 'Método'">
      <template #actions>
        <Button variant="ghost" @click="router.push({ name: 'payment-methods' })">Voltar</Button>
        <PermissionGate v-if="method?.is_active" permission="payment_methods.manage">
          <Button
            variant="secondary"
            @click="router.push({ name: 'payment-methods-edit', params: { id: String(props.methodId) } })"
          >
            Editar
          </Button>
        </PermissionGate>
        <PermissionGate v-if="method?.is_active" permission="payment_methods.manage">
          <Button variant="destructive" @click="confirmOpen = true">Desativar</Button>
        </PermissionGate>
        <PermissionGate v-else-if="method" permission="payment_methods.manage">
          <Button variant="secondary" :loading="reactivating" @click="reactivate()">Reativar</Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="method && !method.is_active" variant="warning" title="Inativo">
      Este método está desativado. Vendas já registradas continuam com o vínculo.
    </Banner>
    <Banner v-if="isError" variant="danger" title="Não encontrado">Método indisponível.</Banner>
    <SurfaceCard v-else-if="isPending">
      <Skeleton class="h-6 w-40" />
    </SurfaceCard>
    <SurfaceCard v-else-if="method">
      <dl class="flex flex-col gap-4">
        <div>
          <dt class="text-[13px] text-muted">Nome</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ method.name }}</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Código</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ method.code }}</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Tipo</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ paymentKindLabel(method.kind) }}</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Cartão</dt>
          <dd class="mt-0.5 text-[15px] text-title">
            {{ method.requires_card_meta ? 'Exige operadora, bandeira e parcelas' : 'Não exige dados de cartão' }}
          </dd>
        </div>
        <div v-if="!method.requires_card_meta">
          <dt class="text-[13px] text-muted">Taxa percentual</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ formatFeePercent(method.fee_percent) }}</dd>
        </div>
        <div v-if="!method.requires_card_meta">
          <dt class="text-[13px] text-muted">Taxa fixa</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ method.fee_fixed ? formatBRL(method.fee_fixed) : '—' }}</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Situação</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ method.is_active ? 'Ativo' : 'Inativo' }}</dd>
        </div>
      </dl>
    </SurfaceCard>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Desativar este método?"
      description="Vendas já registradas não perdem o vínculo. O método some das listas ativas."
      confirm-label="Desativar"
      @confirm="deactivateMutation.mutate()"
    />
  </div>
</template>
