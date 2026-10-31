<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import Banner from '@/components/ui/Banner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import LeadForm from '@/features/leads/LeadForm.vue'
import { createLead, getLead, updateLead } from '@/features/leads/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { LeadPayload, LeadPhoneConflict } from '@/types/lead'
import { ApiError } from '@/types/user'

const props = defineProps<{
  leadId?: number
}>()

const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()
const queryClient = useQueryClient()
const formRef = ref<{
  setErrors: (errors: Record<string, string>) => void
  setDuplicate: (conflict: LeadPhoneConflict | null) => void
} | null>(null)

const isEdit = computed(() => Boolean(props.leadId))
const allowed = computed(() => auth.can('leads.manage'))

const { data: lead, isPending: leadPending, isError: leadError } = useQuery({
  queryKey: ['leads', 'detail', computed(() => props.leadId)],
  queryFn: () => getLead(props.leadId as number),
  enabled: computed(() => Boolean(props.leadId) && allowed.value),
})

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: (payload: LeadPayload) =>
    props.leadId ? updateLead(props.leadId, payload) : createLead(payload),
  onSuccess: async (saved) => {
    toast.success(isEdit.value ? 'Lead atualizado' : 'Lead cadastrado')
    await queryClient.invalidateQueries({ queryKey: ['leads'] })
    await router.push({ name: 'leads-show', params: { id: String(saved.id) } })
  },
  onError: (error) => {
    if (error instanceof ApiError && error.status === 422) {
      const mapped: Record<string, string> = {}
      for (const [field, messages] of Object.entries(error.errors)) {
        mapped[field] = messages[0] ?? error.message
      }
      formRef.value?.setErrors(mapped)
      const duplicate = error.body.duplicate as LeadPhoneConflict | undefined
      if (duplicate) {
        formRef.value?.setDuplicate(duplicate)
      }
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
  if (props.leadId) {
    void router.push({ name: 'leads-show', params: { id: String(props.leadId) } })
    return
  }
  void router.push({ name: 'leads' })
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader
      :title="isEdit ? 'Editar lead' : 'Novo lead'"
      :description="isEdit ? lead?.name : 'Contato que chegou de fora. Você fica registrado como quem cadastrou.'"
    />

    <Banner v-if="!allowed" variant="danger" title="Sem permissão">
      Você não pode {{ isEdit ? 'editar' : 'cadastrar' }} leads.
    </Banner>

    <Banner v-else-if="isEdit && leadError" variant="danger" title="Não encontrado">
      Este lead não está disponível.
    </Banner>

    <SurfaceCard v-else-if="isEdit && leadPending">
      <Skeleton class="h-11" />
      <Skeleton class="mt-3 h-11" />
      <Skeleton class="mt-3 h-24" />
    </SurfaceCard>

    <Banner v-else-if="isEdit && lead?.cancelled_at" variant="warning" title="Lead cancelado">
      Lead cancelado é definitivo e não pode ser editado. Você ainda pode comentar no histórico.
    </Banner>

    <SurfaceCard v-else>
      <LeadForm
        ref="formRef"
        :lead="lead ?? null"
        :submit-label="isEdit ? 'Salvar' : 'Cadastrar'"
        :loading="saving"
        @submit="save"
        @cancel="onCancel"
      />
    </SurfaceCard>
  </div>
</template>
