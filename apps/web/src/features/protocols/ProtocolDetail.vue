<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import ItemLineRow from '@/components/patterns/ItemLineRow.vue'
import MoneyDisplay from '@/components/patterns/MoneyDisplay.vue'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { deactivateProtocol, getProtocol, updateProtocol } from '@/features/protocols/api'
import { formatQty } from '@/lib/formatters'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'

const props = defineProps<{
  protocolId: number
}>()

const router = useRouter()
const toast = useToastStore()
const queryClient = useQueryClient()
const confirmOpen = ref(false)

const idRef = computed(() => props.protocolId)

const { data: protocol, isPending, isError } = useQuery({
  queryKey: ['protocols', idRef],
  queryFn: () => getProtocol(idRef.value),
})

const items = computed(() => protocol.value?.items ?? [])

const deactivateMutation = useMutation({
  mutationFn: () => deactivateProtocol(props.protocolId),
  onSuccess: async () => {
    toast.success('Protocolo desativado')
    await queryClient.invalidateQueries({ queryKey: ['protocols'] })
    await router.push({ name: 'protocols' })
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
  mutationFn: () => updateProtocol(props.protocolId, { is_active: true }),
  onSuccess: async () => {
    toast.success('Protocolo reativado')
    await queryClient.invalidateQueries({ queryKey: ['protocols'] })
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
  void router.push({ name: 'protocols-edit', params: { id: String(props.protocolId) } })
}

function goBack() {
  void router.push({ name: 'protocols' })
}

function itemUnit(item: {
  product?: { unit_of_measure?: { name: string; symbol: string } | null } | null
}) {
  const unit = item.product?.unit_of_measure
  return unit ? `${unit.name} (${unit.symbol})` : ''
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="protocol?.name ?? 'Protocolo'">
      <template #actions>
        <Button variant="ghost" @click="goBack">Voltar</Button>
        <PermissionGate v-if="protocol?.is_active" permission="protocols.update">
          <Button variant="secondary" @click="goEdit">Editar</Button>
        </PermissionGate>
        <PermissionGate v-if="protocol?.is_active" permission="protocols.delete">
          <Button variant="destructive" @click="confirmOpen = true">Desativar</Button>
        </PermissionGate>
        <PermissionGate v-else-if="protocol" permission="protocols.update">
          <Button variant="secondary" :loading="reactivating" @click="reactivate()">
            Reativar
          </Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="protocol && !protocol.is_active" variant="warning" title="Inativo">
      Este protocolo está desativado e não aparece na lista padrão.
    </Banner>

    <Banner v-if="isError" variant="danger" title="Não foi possível carregar">
      O protocolo pode ter sido removido ou você não tem permissão.
    </Banner>

    <SurfaceCard v-else-if="isPending">
      <div class="flex flex-col gap-3">
        <Skeleton class="h-6 w-40" />
        <Skeleton class="h-5 w-56" />
        <Skeleton class="h-5 w-32" />
      </div>
    </SurfaceCard>

    <template v-else-if="protocol">
      <SurfaceCard>
        <dl class="flex flex-col gap-4">
          <div>
            <dt class="text-[13px] text-muted">Descrição</dt>
            <dd class="mt-0.5 whitespace-pre-wrap text-[15px] text-title">
              {{ protocol.description || '—' }}
            </dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Custo total</dt>
            <dd class="mt-0.5">
              <MoneyDisplay :value="protocol.total_cost" />
            </dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Preços de tabela</dt>
            <dd class="mt-0.5">
              <MoneyDisplay :value="protocol.products_sale_total" />
            </dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Valor sugerido</dt>
            <dd class="mt-0.5">
              <MoneyDisplay :value="protocol.suggested_price" />
            </dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Preço mínimo</dt>
            <dd class="mt-0.5">
              <MoneyDisplay :value="protocol.min_price" />
            </dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Condição especial</dt>
            <dd class="mt-0.5">
              <MoneyDisplay :value="protocol.special_price" />
            </dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Margem no sugerido</dt>
            <dd class="mt-0.5">
              <MoneyDisplay :value="protocol.margin_at_suggested" />
            </dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Margem no mínimo</dt>
            <dd class="mt-0.5">
              <MoneyDisplay :value="protocol.margin_at_min" />
            </dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Margem na condição especial</dt>
            <dd class="mt-0.5">
              <MoneyDisplay :value="protocol.margin_at_special" />
            </dd>
          </div>
        </dl>
      </SurfaceCard>

      <div>
        <h2 class="mb-3">Itens</h2>
        <SurfaceCard v-if="items.length === 0" :padding="false">
          <p class="px-5 py-4 text-[15px] text-muted">Nenhum produto neste protocolo.</p>
        </SurfaceCard>
        <SurfaceCard v-else :padding="false">
          <div class="divide-y divide-border-divider px-5 py-2">
            <ItemLineRow
              v-for="item in items"
              :key="item.id"
              :title="item.product?.name ?? `Produto #${item.product_id}`"
              :unit="itemUnit(item)"
              :line-sale="item.line_sale"
              :quantity="formatQty(item.quantity)"
              readonly
            />
          </div>
        </SurfaceCard>
      </div>
    </template>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Desativar este protocolo?"
      description="O cadastro permanece no histórico. Você pode reativar depois."
      confirm-label="Desativar"
      @confirm="deactivateMutation.mutate()"
    />
  </div>
</template>
