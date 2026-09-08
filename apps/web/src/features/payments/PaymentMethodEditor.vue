<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import Banner from '@/components/ui/Banner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { createPaymentMethod, getPaymentMethod, updatePaymentMethod } from '@/features/payments/api'
import PaymentMethodForm from '@/features/payments/PaymentMethodForm.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { PaymentMethodPayload } from '@/types/sale'
import { ApiError } from '@/types/user'

const props = defineProps<{
  methodId?: number
}>()

const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()
const queryClient = useQueryClient()
const formRef = ref<{ setErrors: (errors: Record<string, string>) => void } | null>(null)
const isEdit = computed(() => Boolean(props.methodId))
const allowed = computed(() => auth.can('payment_methods.manage'))

const { data: method, isPending, isError } = useQuery({
  queryKey: ['payment-methods', computed(() => props.methodId)],
  queryFn: () => getPaymentMethod(props.methodId as number),
  enabled: computed(() => Boolean(props.methodId) && allowed.value),
})

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: (payload: PaymentMethodPayload) =>
    props.methodId ? updatePaymentMethod(props.methodId, payload) : createPaymentMethod(payload),
  onSuccess: async (saved) => {
    toast.success(isEdit.value ? 'Método atualizado' : 'Método cadastrado')
    await queryClient.invalidateQueries({ queryKey: ['payment-methods'] })
    await router.push({ name: 'payment-methods-show', params: { id: String(saved.id) } })
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
    props.methodId
      ? { name: 'payment-methods-show', params: { id: String(props.methodId) } }
      : { name: 'payment-methods' },
  )
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="isEdit ? 'Editar método' : 'Novo método'" />
    <Banner v-if="!allowed" variant="danger" title="Sem permissão">
      Você não pode gerenciar métodos de pagamento.
    </Banner>
    <Banner v-else-if="isEdit && isError" variant="danger" title="Não encontrado">
      Este método não está disponível.
    </Banner>
    <SurfaceCard v-else-if="isEdit && isPending">
      <Skeleton class="h-11" />
    </SurfaceCard>
    <SurfaceCard v-else>
      <PaymentMethodForm
        ref="formRef"
        :method="method ?? null"
        :submit-label="isEdit ? 'Salvar' : 'Cadastrar'"
        :loading="saving"
        @submit="save"
        @cancel="onCancel"
      />
    </SurfaceCard>
  </div>
</template>
