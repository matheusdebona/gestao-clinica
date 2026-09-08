<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import Banner from '@/components/ui/Banner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { createCardBrand, getCardBrand, updateCardBrand } from '@/features/payments/api'
import CardBrandForm from '@/features/payments/CardBrandForm.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { CardBrandPayload } from '@/types/sale'
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
const allowed = computed(() => auth.can('card_brands.manage'))

const { data: brand, isPending, isError } = useQuery({
  queryKey: ['card-brands', computed(() => props.brandId)],
  queryFn: () => getCardBrand(props.brandId as number),
  enabled: computed(() => Boolean(props.brandId) && allowed.value),
})

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: (payload: CardBrandPayload) =>
    props.brandId ? updateCardBrand(props.brandId, payload) : createCardBrand(payload),
  onSuccess: async (saved) => {
    toast.success(isEdit.value ? 'Bandeira atualizada' : 'Bandeira cadastrada')
    await queryClient.invalidateQueries({ queryKey: ['card-brands'] })
    await router.push({ name: 'card-brands-show', params: { id: String(saved.id) } })
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
      ? { name: 'card-brands-show', params: { id: String(props.brandId) } }
      : { name: 'card-brands' },
  )
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="isEdit ? 'Editar bandeira' : 'Nova bandeira'" />
    <Banner v-if="!allowed" variant="danger" title="Sem permissão">Você não pode gerenciar bandeiras.</Banner>
    <Banner v-else-if="isEdit && isError" variant="danger" title="Não encontrado">
      Esta bandeira não está disponível.
    </Banner>
    <SurfaceCard v-else-if="isEdit && isPending">
      <Skeleton class="h-11" />
    </SurfaceCard>
    <SurfaceCard v-else>
      <CardBrandForm
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
