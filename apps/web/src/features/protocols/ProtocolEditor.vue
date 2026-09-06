<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import Banner from '@/components/ui/Banner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import {
  createProtocol,
  getProtocol,
  syncProtocolItems,
  updateProtocol,
} from '@/features/protocols/api'
import { clearProtocolDraft } from '@/features/protocols/draft'
import ProtocolForm from '@/features/protocols/ProtocolForm.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { ProtocolSavePayload } from '@/types/protocol'
import { ApiError } from '@/types/user'

const props = defineProps<{
  protocolId?: number
}>()

const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()
const queryClient = useQueryClient()
const formRef = ref<{
  setErrors: (errors: Record<string, string>) => void
  clearDraft: () => void
} | null>(null)

const isEdit = computed(() => Boolean(props.protocolId))
const requiredPermission = computed(() => (isEdit.value ? 'protocols.update' : 'protocols.create'))
const allowed = computed(() => auth.can(requiredPermission.value))

const {
  data: protocol,
  isPending: protocolPending,
  isError: protocolError,
} = useQuery({
  queryKey: ['protocols', computed(() => props.protocolId)],
  queryFn: () => getProtocol(props.protocolId as number),
  enabled: computed(() => Boolean(props.protocolId) && allowed.value),
})

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: async (payload: ProtocolSavePayload) => {
    if (props.protocolId) {
      await updateProtocol(props.protocolId, payload.header)
      return syncProtocolItems(props.protocolId, payload.items)
    }
    return createProtocol({
      ...payload.header,
      items: payload.items,
    })
  },
  onSuccess: async (saved) => {
    clearProtocolDraft(props.protocolId)
    formRef.value?.clearDraft()
    toast.success(isEdit.value ? 'Protocolo atualizado' : 'Protocolo cadastrado')
    await queryClient.invalidateQueries({ queryKey: ['protocols'] })
    await router.push({ name: 'protocols-show', params: { id: String(saved.id) } })
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
  clearProtocolDraft(props.protocolId)
  if (props.protocolId) {
    void router.push({ name: 'protocols-show', params: { id: String(props.protocolId) } })
    return
  }
  void router.push({ name: 'protocols' })
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader
      :title="isEdit ? 'Editar protocolo' : 'Novo protocolo'"
      :description="isEdit ? protocol?.name : 'Pacote de produtos com custo, sugerido e mínimo.'"
    />

    <Banner v-if="!allowed" variant="danger" title="Sem permissão">
      Você não pode {{ isEdit ? 'editar' : 'criar' }} protocolos.
    </Banner>

    <Banner v-else-if="isEdit && protocolError" variant="danger" title="Não encontrado">
      Este protocolo não está disponível.
    </Banner>

    <SurfaceCard v-else-if="isEdit && protocolPending">
      <Skeleton class="h-11" />
      <Skeleton class="mt-3 h-11" />
      <Skeleton class="mt-3 h-24" />
    </SurfaceCard>

    <SurfaceCard v-else>
      <ProtocolForm
        ref="formRef"
        :protocol="protocol ?? null"
        :protocol-id="protocolId"
        :submit-label="isEdit ? 'Salvar' : 'Cadastrar'"
        :loading="saving"
        @submit="save"
        @cancel="onCancel"
      />
    </SurfaceCard>
  </div>
</template>
