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
import { deactivateProductType, getProductType, updateProductType } from '@/features/catalog/api'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'

const props = defineProps<{
  productTypeId: number
}>()

const router = useRouter()
const toast = useToastStore()
const queryClient = useQueryClient()
const confirmOpen = ref(false)
const idRef = computed(() => props.productTypeId)

const { data: productType, isPending, isError } = useQuery({
  queryKey: ['product-types', idRef],
  queryFn: () => getProductType(idRef.value),
})

const deactivateMutation = useMutation({
  mutationFn: () => deactivateProductType(props.productTypeId),
  onSuccess: async () => {
    toast.success('Tipo desativado')
    await queryClient.invalidateQueries({ queryKey: ['product-types'] })
    await router.push({ name: 'product-types' })
  },
  onError: (error) => {
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível desativar.')
  },
})

const { mutate: reactivate, isPending: reactivating } = useMutation({
  mutationFn: () => updateProductType(props.productTypeId, { is_active: true }),
  onSuccess: async () => {
    toast.success('Tipo reativado')
    await queryClient.invalidateQueries({ queryKey: ['product-types'] })
  },
})
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="productType?.name ?? 'Tipo'">
      <template #actions>
        <Button variant="ghost" @click="router.push({ name: 'product-types' })">Voltar</Button>
        <PermissionGate v-if="productType?.is_active" permission="product_types.manage">
          <Button
            variant="secondary"
            @click="router.push({ name: 'product-types-edit', params: { id: String(props.productTypeId) } })"
          >
            Editar
          </Button>
        </PermissionGate>
        <PermissionGate v-if="productType?.is_active" permission="product_types.manage">
          <Button variant="destructive" @click="confirmOpen = true">Desativar</Button>
        </PermissionGate>
        <PermissionGate v-else-if="productType" permission="product_types.manage">
          <Button variant="secondary" :loading="reactivating" @click="reactivate()">Reativar</Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="productType && !productType.is_active" variant="warning" title="Inativo">
      Este tipo está desativado.
    </Banner>
    <Banner v-if="isError" variant="danger" title="Não encontrado">Tipo indisponível.</Banner>
    <SurfaceCard v-else-if="isPending">
      <Skeleton class="h-6 w-40" />
    </SurfaceCard>
    <SurfaceCard v-else-if="productType">
      <dl class="flex flex-col gap-4">
        <div>
          <dt class="text-[13px] text-muted">Nome</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ productType.name }}</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Marca</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ productType.brand?.name ?? '—' }}</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Situação</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ productType.is_active ? 'Ativo' : 'Inativo' }}</dd>
        </div>
      </dl>
    </SurfaceCard>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Desativar este tipo?"
      description="Produtos ligados a ele continuam no histórico."
      confirm-label="Desativar"
      @confirm="deactivateMutation.mutate()"
    />
  </div>
</template>
