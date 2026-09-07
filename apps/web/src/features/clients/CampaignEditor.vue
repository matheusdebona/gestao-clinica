<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Banner from '@/components/ui/Banner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { createCampaign, getCampaign, updateCampaign } from '@/features/clients/api'
import CampaignForm from '@/features/clients/CampaignForm.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { CampaignPayload } from '@/types/client'
import { ApiError } from '@/types/user'

const props = defineProps<{
  campaignId?: number
}>()

const route = useRoute()
const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()
const queryClient = useQueryClient()
const formRef = ref<{ setErrors: (errors: Record<string, string>) => void } | null>(null)
const isEdit = computed(() => Boolean(props.campaignId))
const allowed = computed(() => auth.can('campaigns.manage'))

const initialOriginId = computed(() => {
  const raw = route.query.client_origin_id
  return typeof raw === 'string' ? raw : ''
})

const { data: campaign, isPending, isError } = useQuery({
  queryKey: ['campaigns', computed(() => props.campaignId)],
  queryFn: () => getCampaign(props.campaignId as number),
  enabled: computed(() => Boolean(props.campaignId) && allowed.value),
})

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: (payload: CampaignPayload) =>
    props.campaignId ? updateCampaign(props.campaignId, payload) : createCampaign(payload),
  onSuccess: async (saved) => {
    toast.success(isEdit.value ? 'Campanha atualizada' : 'Campanha cadastrada')
    await queryClient.invalidateQueries({ queryKey: ['campaigns'] })
    await router.push({ name: 'campaigns-show', params: { id: String(saved.id) } })
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
    props.campaignId
      ? { name: 'campaigns-show', params: { id: String(props.campaignId) } }
      : {
          name: 'campaigns',
          query: initialOriginId.value ? { client_origin_id: initialOriginId.value } : {},
        },
  )
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader
      :title="isEdit ? 'Editar campanha' : 'Nova campanha'"
      description="A campanha sempre fica ligada a uma origem."
    />
    <Banner v-if="!allowed" variant="danger" title="Sem permissão">Você não pode gerenciar campanhas.</Banner>
    <Banner v-else-if="isEdit && isError" variant="danger" title="Não encontrado">Esta campanha não está disponível.</Banner>
    <SurfaceCard v-else-if="isEdit && isPending">
      <Skeleton class="h-11" />
    </SurfaceCard>
    <SurfaceCard v-else>
      <CampaignForm
        ref="formRef"
        :campaign="campaign ?? null"
        :initial-origin-id="initialOriginId"
        :submit-label="isEdit ? 'Salvar' : 'Cadastrar'"
        :loading="saving"
        @submit="save"
        @cancel="onCancel"
      />
    </SurfaceCard>
  </div>
</template>
