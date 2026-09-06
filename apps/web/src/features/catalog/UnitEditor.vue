<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import Banner from '@/components/ui/Banner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import UnitForm from '@/features/catalog/UnitForm.vue'
import { createUnit, getUnit, updateUnit } from '@/features/catalog/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { UnitPayload } from '@/types/catalog'
import { ApiError } from '@/types/user'

const props = defineProps<{
  unitId?: number
}>()

const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()
const queryClient = useQueryClient()
const formRef = ref<{ setErrors: (errors: Record<string, string>) => void } | null>(null)
const isEdit = computed(() => Boolean(props.unitId))
const allowed = computed(() => auth.can('units.manage'))

const { data: unit, isPending, isError } = useQuery({
  queryKey: ['units', computed(() => props.unitId)],
  queryFn: () => getUnit(props.unitId as number),
  enabled: computed(() => Boolean(props.unitId) && allowed.value),
})

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: (payload: UnitPayload) =>
    props.unitId ? updateUnit(props.unitId, payload) : createUnit(payload),
  onSuccess: async (saved) => {
    toast.success(isEdit.value ? 'Unidade atualizada' : 'Unidade cadastrada')
    await queryClient.invalidateQueries({ queryKey: ['units'] })
    await router.push({ name: 'units-show', params: { id: String(saved.id) } })
  },
  onError: (error) => {
    if (error instanceof ApiError && error.status === 422) {
      const mapped: Record<string, string> = {}
      for (const [field, messages] of Object.entries(error.errors)) {
        mapped[field] = messages[0] ?? error.message
      }
      formRef.value?.setErrors(mapped)
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível salvar.')
  },
})

function onCancel() {
  void router.push(
    props.unitId ? { name: 'units-show', params: { id: String(props.unitId) } } : { name: 'units' },
  )
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="isEdit ? 'Editar unidade' : 'Nova unidade'" />
    <Banner v-if="!allowed" variant="danger" title="Sem permissão">Você não pode gerenciar unidades.</Banner>
    <Banner v-else-if="isEdit && isError" variant="danger" title="Não encontrado">Esta unidade não está disponível.</Banner>
    <SurfaceCard v-else-if="isEdit && isPending">
      <Skeleton class="h-11" />
    </SurfaceCard>
    <SurfaceCard v-else>
      <UnitForm
        ref="formRef"
        :unit="unit ?? null"
        :submit-label="isEdit ? 'Salvar' : 'Cadastrar'"
        :loading="saving"
        @submit="save"
        @cancel="onCancel"
      />
    </SurfaceCard>
  </div>
</template>
