<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import Banner from '@/components/ui/Banner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { createCardOperator, getCardOperator, updateCardOperator } from '@/features/payments/api'
import CardOperatorForm from '@/features/payments/CardOperatorForm.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { CardOperatorPayload } from '@/types/sale'
import { ApiError } from '@/types/user'

const props = defineProps<{
  operatorId?: number
}>()

const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()
const queryClient = useQueryClient()
const formRef = ref<{ setErrors: (errors: Record<string, string>) => void } | null>(null)
const isEdit = computed(() => Boolean(props.operatorId))
const allowed = computed(() => auth.can('card_operators.manage'))

const { data: operator, isPending, isError } = useQuery({
  queryKey: ['card-operators', computed(() => props.operatorId)],
  queryFn: () => getCardOperator(props.operatorId as number),
  enabled: computed(() => Boolean(props.operatorId) && allowed.value),
})

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: (payload: CardOperatorPayload) =>
    props.operatorId ? updateCardOperator(props.operatorId, payload) : createCardOperator(payload),
  onSuccess: async (saved) => {
    toast.success(isEdit.value ? 'Operadora atualizada' : 'Operadora cadastrada')
    await queryClient.invalidateQueries({ queryKey: ['card-operators'] })
    await router.push({ name: 'card-operators-show', params: { id: String(saved.id) } })
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
    props.operatorId
      ? { name: 'card-operators-show', params: { id: String(props.operatorId) } }
      : { name: 'card-operators' },
  )
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="isEdit ? 'Editar operadora' : 'Nova operadora'" />
    <Banner v-if="!allowed" variant="danger" title="Sem permissão">Você não pode gerenciar operadoras.</Banner>
    <Banner v-else-if="isEdit && isError" variant="danger" title="Não encontrado">
      Esta operadora não está disponível.
    </Banner>
    <SurfaceCard v-else-if="isEdit && isPending">
      <Skeleton class="h-11" />
    </SurfaceCard>
    <SurfaceCard v-else>
      <CardOperatorForm
        ref="formRef"
        :operator="operator ?? null"
        :submit-label="isEdit ? 'Salvar' : 'Cadastrar'"
        :loading="saving"
        @submit="save"
        @cancel="onCancel"
      />
    </SurfaceCard>
  </div>
</template>
