<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import Banner from '@/components/ui/Banner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import BrandForm from '@/features/catalog/BrandForm.vue'
import { createBrand, getBrand, updateBrand } from '@/features/catalog/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { BrandPayload } from '@/types/catalog'
import { ApiError } from '@/types/user'

const props = defineProps<{
  brandId?: number
}>()

const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()
const queryClient = useQueryClient()
const formRef = ref<{ setErrors: (errors: Record<string, string>) => void } | null>(null)
const isEdit = computed(() => Boolean(props.brandId))
const allowed = computed(() => auth.can('brands.manage'))

const { data: brand, isPending, isError } = useQuery({
  queryKey: ['brands', computed(() => props.brandId)],
  queryFn: () => getBrand(props.brandId as number),
  enabled: computed(() => Boolean(props.brandId) && allowed.value),
})

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: (payload: BrandPayload) =>
    props.brandId ? updateBrand(props.brandId, payload) : createBrand(payload),
  onSuccess: async (saved) => {
    toast.success(isEdit.value ? 'Marca atualizada' : 'Marca cadastrada')
    await queryClient.invalidateQueries({ queryKey: ['brands'] })
    await router.push({ name: 'brands-show', params: { id: String(saved.id) } })
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
    props.brandId
      ? { name: 'brands-show', params: { id: String(props.brandId) } }
      : { name: 'brands' },
  )
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="isEdit ? 'Editar marca' : 'Nova marca'" />
    <Banner v-if="!allowed" variant="danger" title="Sem permissão">Você não pode gerenciar marcas.</Banner>
    <Banner v-else-if="isEdit && isError" variant="danger" title="Não encontrado">Esta marca não está disponível.</Banner>
    <SurfaceCard v-else-if="isEdit && isPending">
      <Skeleton class="h-11" />
    </SurfaceCard>
    <SurfaceCard v-else>
      <BrandForm
        ref="formRef"
        :brand="brand ?? null"
        :submit-label="isEdit ? 'Salvar' : 'Cadastrar'"
        :loading="saving"
        @submit="save"
        @cancel="onCancel"
      />
    </SurfaceCard>
  </div>
</template>
