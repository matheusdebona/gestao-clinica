<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import Banner from '@/components/ui/Banner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import ProductTypeForm from '@/features/catalog/ProductTypeForm.vue'
import { createProductType, getProductType, updateProductType } from '@/features/catalog/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { ProductTypePayload } from '@/types/catalog'
import { ApiError } from '@/types/user'

const props = defineProps<{
  productTypeId?: number
}>()

const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()
const queryClient = useQueryClient()
const formRef = ref<{ setErrors: (errors: Record<string, string>) => void } | null>(null)
const isEdit = computed(() => Boolean(props.productTypeId))
const allowed = computed(() => auth.can('product_types.manage'))

const { data: productType, isPending, isError } = useQuery({
  queryKey: ['product-types', computed(() => props.productTypeId)],
  queryFn: () => getProductType(props.productTypeId as number),
  enabled: computed(() => Boolean(props.productTypeId) && allowed.value),
})

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: (payload: ProductTypePayload) =>
    props.productTypeId ? updateProductType(props.productTypeId, payload) : createProductType(payload),
  onSuccess: async (saved) => {
    toast.success(isEdit.value ? 'Tipo atualizado' : 'Tipo cadastrado')
    await queryClient.invalidateQueries({ queryKey: ['product-types'] })
    await router.push({ name: 'product-types-show', params: { id: String(saved.id) } })
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
    props.productTypeId
      ? { name: 'product-types-show', params: { id: String(props.productTypeId) } }
      : { name: 'product-types' },
  )
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader
      :title="isEdit ? 'Editar tipo' : 'Novo tipo'"
      description="O tipo sempre fica ligado a uma marca."
    />
    <Banner v-if="!allowed" variant="danger" title="Sem permissão">Você não pode gerenciar tipos.</Banner>
    <Banner v-else-if="isEdit && isError" variant="danger" title="Não encontrado">Este tipo não está disponível.</Banner>
    <SurfaceCard v-else-if="isEdit && isPending">
      <Skeleton class="h-11" />
    </SurfaceCard>
    <SurfaceCard v-else>
      <ProductTypeForm
        ref="formRef"
        :product-type="productType ?? null"
        :submit-label="isEdit ? 'Salvar' : 'Cadastrar'"
        :loading="saving"
        @submit="save"
        @cancel="onCancel"
      />
    </SurfaceCard>
  </div>
</template>
