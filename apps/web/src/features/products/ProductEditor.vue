<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Banner from '@/components/ui/Banner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import ProductForm from '@/features/products/ProductForm.vue'
import { createProduct, getProduct, updateProduct } from '@/features/products/api'
import { safeAppPath } from '@/lib/return-to'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { ProductPayload } from '@/types/product'
import { ApiError } from '@/types/user'

const props = defineProps<{
  productId?: number
}>()

const route = useRoute()
const router = useRouter()
const toast = useToastStore()

const returnTo = computed(() => safeAppPath(route.query.returnTo))
const auth = useAuthStore()
const queryClient = useQueryClient()
const formRef = ref<{ setErrors: (errors: Record<string, string>) => void } | null>(null)

const isEdit = computed(() => Boolean(props.productId))
const requiredPermission = computed(() => (isEdit.value ? 'products.update' : 'products.create'))
const allowed = computed(() => auth.can(requiredPermission.value))

const {
  data: product,
  isPending: productPending,
  isError: productError,
} = useQuery({
  queryKey: ['products', computed(() => props.productId)],
  queryFn: () => getProduct(props.productId as number),
  enabled: computed(() => Boolean(props.productId) && allowed.value),
})

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: (payload: ProductPayload) => {
    if (props.productId) {
      return updateProduct(props.productId, payload)
    }
    return createProduct(payload)
  },
  onSuccess: async (saved) => {
    toast.success(isEdit.value ? 'Produto atualizado' : 'Produto cadastrado')
    await queryClient.invalidateQueries({ queryKey: ['products'] })
    if (!isEdit.value && returnTo.value) {
      await router.push({
        path: returnTo.value,
        query: { selectProduct: String(saved.id) },
      })
      return
    }
    await router.push({ name: 'products-show', params: { id: String(saved.id) } })
  },
  onError: (error) => {
    if (error instanceof ApiError && error.status === 422) {
      const mapped: Record<string, string> = {}
      for (const [field, messages] of Object.entries(error.errors)) {
        mapped[field] = messages[0] ?? error.message
      }
      formRef.value?.setErrors(mapped)
      if (!Object.keys(mapped).length) {
        toast.error(error.message)
      }
      return
    }
    if (error instanceof ApiError && error.status === 403) {
      toast.error('Sem permissão.')
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível salvar.')
  },
})

function onCancel() {
  if (returnTo.value) {
    void router.push(returnTo.value)
    return
  }
  if (props.productId) {
    void router.push({ name: 'products-show', params: { id: String(props.productId) } })
    return
  }
  void router.push({ name: 'products' })
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader
      :title="isEdit ? 'Editar produto' : 'Novo produto'"
      :description="isEdit ? product?.name : 'Marca, tipo e unidade da clínica.'"
    />

    <Banner v-if="!allowed" variant="danger" title="Sem permissão">
      Você não pode {{ isEdit ? 'editar' : 'criar' }} produtos.
    </Banner>

    <Banner v-else-if="isEdit && productError" variant="danger" title="Não encontrado">
      Este produto não está disponível.
    </Banner>

    <SurfaceCard v-else-if="isEdit && productPending">
      <Skeleton class="h-11" />
      <Skeleton class="mt-3 h-11" />
      <Skeleton class="mt-3 h-24" />
    </SurfaceCard>

    <SurfaceCard v-else>
      <ProductForm
        ref="formRef"
        :product="product ?? null"
        :submit-label="isEdit ? 'Salvar' : 'Cadastrar'"
        :loading="saving"
        @submit="save"
        @cancel="onCancel"
      />
    </SurfaceCard>
  </div>
</template>
