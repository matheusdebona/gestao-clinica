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
import { deactivateUnit, getUnit, updateUnit } from '@/features/catalog/api'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'

const props = defineProps<{
  unitId: number
}>()

const router = useRouter()
const toast = useToastStore()
const queryClient = useQueryClient()
const confirmOpen = ref(false)
const idRef = computed(() => props.unitId)

const { data: unit, isPending, isError } = useQuery({
  queryKey: ['units', idRef],
  queryFn: () => getUnit(idRef.value),
})

const deactivateMutation = useMutation({
  mutationFn: () => deactivateUnit(props.unitId),
  onSuccess: async () => {
    toast.success('Unidade desativada')
    await queryClient.invalidateQueries({ queryKey: ['units'] })
    await router.push({ name: 'units' })
  },
  onError: (error) => {
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível desativar.')
  },
})

const { mutate: reactivate, isPending: reactivating } = useMutation({
  mutationFn: () => updateUnit(props.unitId, { is_active: true }),
  onSuccess: async () => {
    toast.success('Unidade reativada')
    await queryClient.invalidateQueries({ queryKey: ['units'] })
  },
})
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="unit?.name ?? 'Unidade'">
      <template #actions>
        <Button variant="ghost" @click="router.push({ name: 'units' })">Voltar</Button>
        <PermissionGate v-if="unit?.is_active" permission="units.manage">
          <Button variant="secondary" @click="router.push({ name: 'units-edit', params: { id: String(props.unitId) } })">
            Editar
          </Button>
        </PermissionGate>
        <PermissionGate v-if="unit?.is_active" permission="units.manage">
          <Button variant="destructive" @click="confirmOpen = true">Desativar</Button>
        </PermissionGate>
        <PermissionGate v-else-if="unit" permission="units.manage">
          <Button variant="secondary" :loading="reactivating" @click="reactivate()">Reativar</Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="unit && !unit.is_active" variant="warning" title="Inativa">
      Esta unidade está desativada.
    </Banner>
    <Banner v-if="isError" variant="danger" title="Não encontrado">Unidade indisponível.</Banner>
    <SurfaceCard v-else-if="isPending">
      <Skeleton class="h-6 w-40" />
    </SurfaceCard>
    <SurfaceCard v-else-if="unit">
      <dl class="flex flex-col gap-4">
        <div>
          <dt class="text-[13px] text-muted">Nome</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ unit.name }}</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Símbolo</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ unit.symbol }}</dd>
        </div>
        <div>
          <dt class="text-[13px] text-muted">Situação</dt>
          <dd class="mt-0.5 text-[15px] text-title">{{ unit.is_active ? 'Ativa' : 'Inativa' }}</dd>
        </div>
      </dl>
    </SurfaceCard>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Desativar esta unidade?"
      description="Produtos que usam esta unidade continuam no histórico."
      confirm-label="Desativar"
      @confirm="deactivateMutation.mutate()"
    />
  </div>
</template>
