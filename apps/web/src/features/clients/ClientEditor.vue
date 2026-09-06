<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import Banner from '@/components/ui/Banner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import ClientForm from '@/features/clients/ClientForm.vue'
import { createClient, getClient, updateClient } from '@/features/clients/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { ClientPayload } from '@/types/client'
import { ApiError } from '@/types/user'

const props = defineProps<{
  clientId?: number
}>()

const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()
const queryClient = useQueryClient()
const formRef = ref<{ setErrors: (errors: Record<string, string>) => void } | null>(null)

const isEdit = computed(() => Boolean(props.clientId))
const requiredPermission = computed(() => (isEdit.value ? 'clients.update' : 'clients.create'))
const allowed = computed(() => auth.can(requiredPermission.value))

const {
  data: client,
  isPending: clientPending,
  isError: clientError,
} = useQuery({
  queryKey: ['clients', computed(() => props.clientId)],
  queryFn: () => getClient(props.clientId as number),
  enabled: computed(() => Boolean(props.clientId) && allowed.value),
})

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: (payload: ClientPayload) => {
    if (props.clientId) {
      return updateClient(props.clientId, payload)
    }
    return createClient(payload)
  },
  onSuccess: async (client) => {
    toast.success(isEdit.value ? 'Cliente atualizado' : 'Cliente cadastrado')
    await queryClient.invalidateQueries({ queryKey: ['clients'] })
    await router.push({ name: 'clients-show', params: { id: String(client.id) } })
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
  if (props.clientId) {
    void router.push({ name: 'clients-show', params: { id: String(props.clientId) } })
    return
  }
  void router.push({ name: 'clients' })
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader
      :title="isEdit ? 'Editar cliente' : 'Novo cliente'"
      :description="isEdit ? client?.name : 'Cadastro na clínica atual.'"
    />

    <Banner v-if="!allowed" variant="danger" title="Sem permissão">
      Você não pode {{ isEdit ? 'editar' : 'criar' }} clientes.
    </Banner>

    <Banner v-else-if="isEdit && clientError" variant="danger" title="Não encontrado">
      Este cliente não está disponível.
    </Banner>

    <SurfaceCard v-else-if="isEdit && clientPending">
      <Skeleton class="h-11" />
      <Skeleton class="mt-3 h-11" />
      <Skeleton class="mt-3 h-24" />
    </SurfaceCard>

    <SurfaceCard v-else>
      <ClientForm
        ref="formRef"
        :client="client ?? null"
        :submit-label="isEdit ? 'Salvar' : 'Cadastrar'"
        :loading="saving"
        @submit="save"
        @cancel="onCancel"
      />
    </SurfaceCard>
  </div>
</template>
