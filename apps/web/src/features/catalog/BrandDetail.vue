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
import { deactivateBrand, getBrand, updateBrand } from '@/features/catalog/api'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'

const props = defineProps<{
  brandId: number
}>()

const router = useRouter()
const toast = useToastStore()
const queryClient = useQueryClient()
const confirmOpen = ref(false)
const idRef = computed(() => props.brandId)

const { data: brand, isPending, isError } = useQuery({
  queryKey: ['brands', idRef],
  queryFn: () => getBrand(idRef.value),
})

const deactivateMutation = useMutation({
  mutationFn: () => deactivateBrand(props.brandId),
  onSuccess: async () => {
    toast.success('Marca desativada')
    await queryClient.invalidateQueries({ queryKey: ['brands'] })
    await router.push({ name: 'brands' })
  },
  onError: (error) => {
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível desativar.')
  },
})

const { mutate: reactivate, isPending: reactivating } = useMutation({
  mutationFn: () => updateBrand(props.brandId, { is_active: true }),
  onSuccess: async () => {
    toast.success('Marca reativada')
    await queryClient.invalidateQueries({ queryKey: ['brands'] })
  },
})
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="brand?.name ?? 'Marca'">
      <template #actions>
        <Button variant="ghost" @click="router.push({ name: 'brands' })">Voltar</Button>
        <PermissionGate v-if="brand?.is_active" permission="brands.manage">
          <Button variant="secondary" @click="router.push({ name: 'brands-edit', params: { id: String(props.brandId) } })">
            Editar
          </Button>
        </PermissionGate>
        <PermissionGate v-if="brand?.is_active" permission="brands.manage">
          <Button variant="destructive" @click="confirmOpen = true">Desativar</Button>
        </PermissionGate>
        <PermissionGate v-else-if="brand" permission="brands.manage">
          <Button variant="secondary" :loading="reactivating" @click="reactivate()">Reativar</Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="brand && !brand.is_active" variant="warning" title="Inativa">
      Esta marca está desativada.
    </Banner>
    <Banner v-if="isError" variant="danger" title="Não encontrado">Marca indisponível.</Banner>
    <SurfaceCard v-else-if="isPending">
      <Skeleton class="h-6 w-40" />
    </SurfaceCard>
    <SurfaceCard v-else-if="brand">
      <dl class="flex flex-col gap-4">
        <div>
          <dt class="text-[13px] text-muted">Nome</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ brand.name }}</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Situação</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ brand.is_active ? 'Ativa' : 'Inativa' }}</dd>
        </div>
      </dl>
    </SurfaceCard>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Desativar esta marca?"
      description="Tipos e produtos ligados a ela continuam no histórico."
      confirm-label="Desativar"
      @confirm="deactivateMutation.mutate()"
    />
  </div>
</template>
