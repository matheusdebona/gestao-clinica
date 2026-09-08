<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import Banner from '@/components/ui/Banner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import {
  createClientOrigin,
  getClientOrigin,
  updateClientOrigin,
} from '@/features/clients/api'
import ClientOriginForm from '@/features/clients/ClientOriginForm.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { ClientOriginPayload } from '@/types/client'
import { ApiError } from '@/types/user'

const props = defineProps<{
  originId?: number
}>()

const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()
const queryClient = useQueryClient()
const formRef = ref<{ setErrors: (errors: Record<string, string>) => void } | null>(null)
const isEdit = computed(() => Boolean(props.originId))
const allowed = computed(() => auth.can('client_origins.manage'))

const { data: origin, isPending, isError } = useQuery({
  queryKey: ['client-origins', computed(() => props.originId)],
  queryFn: () => getClientOrigin(props.originId as number),
  enabled: computed(() => Boolean(props.originId) && allowed.value),
})

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: (payload: ClientOriginPayload) =>
    props.originId ? updateClientOrigin(props.originId, payload) : createClientOrigin(payload),
  onSuccess: async (saved) => {
    toast.success(isEdit.value ? 'Origem atualizada' : 'Origem cadastrada')
    await queryClient.invalidateQueries({ queryKey: ['client-origins'] })
    await router.push({ name: 'client-origins-show', params: { id: String(saved.id) } })
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
    props.originId
      ? { name: 'client-origins-show', params: { id: String(props.originId) } }
      : { name: 'client-origins' },
  )
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="isEdit ? 'Editar origem' : 'Nova origem'" />
    <Banner v-if="!allowed" variant="danger" title="Sem permissão">Você não pode gerenciar origens.</Banner>
    <Banner v-else-if="isEdit && isError" variant="danger" title="Não encontrado">Esta origem não está disponível.</Banner>
    <SurfaceCard v-else-if="isEdit && isPending">
      <Skeleton class="h-11" />
    </SurfaceCard>
    <SurfaceCard v-else>
      <ClientOriginForm
        ref="formRef"
        :origin="origin ?? null"
        :submit-label="isEdit ? 'Salvar' : 'Cadastrar'"
        :loading="saving"
        @submit="save"
        @cancel="onCancel"
      />
    </SurfaceCard>
  </div>
</template>
