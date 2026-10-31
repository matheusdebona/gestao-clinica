<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import Badge from '@/components/ui/Badge.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import LeadConvertDialog from '@/features/leads/LeadConvertDialog.vue'
import LeadHistory from '@/features/leads/LeadHistory.vue'
import LeadStatusDialog from '@/features/leads/LeadStatusDialog.vue'
import { deleteLead, getLead } from '@/features/leads/api'
import { useLeadCatalogs } from '@/features/leads/useLeadCatalogs'
import { formatDateTime } from '@/lib/formatters'
import { formatPhoneBR } from '@/lib/masks'
import { useToastStore } from '@/stores/toast'
import type { LeadConvertResult } from '@/types/lead'
import { ApiError } from '@/types/user'

const props = defineProps<{
  leadId: number
}>()

const router = useRouter()
const toast = useToastStore()
const queryClient = useQueryClient()
const { statuses } = useLeadCatalogs()

const statusOpen = ref(false)
const convertOpen = ref(false)
const deleteOpen = ref(false)
const idRef = computed(() => props.leadId)

const { data: lead, isPending, isError } = useQuery({
  queryKey: ['leads', 'detail', idRef],
  queryFn: () => getLead(idRef.value),
})

const isConverted = computed(() => Boolean(lead.value?.client_id))

const deleteMutation = useMutation({
  mutationFn: () => deleteLead(props.leadId),
  onSuccess: async () => {
    toast.success('Lead excluído')
    await queryClient.invalidateQueries({ queryKey: ['leads'] })
    await router.push({ name: 'leads' })
  },
  onError: (error) => {
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível excluir.')
  },
})

function goEdit() {
  void router.push({ name: 'leads-edit', params: { id: String(props.leadId) } })
}

function goBack() {
  void router.push({ name: 'leads' })
}

function openClient() {
  if (lead.value?.client_id) {
    void router.push({ name: 'clients-show', params: { id: String(lead.value.client_id) } })
  }
}

function onConverted(result: LeadConvertResult) {
  void router.push({ name: 'appointments-show', params: { id: String(result.appointment.id) } })
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="lead?.name ?? 'Lead'">
      <template #actions>
        <Button variant="ghost" @click="goBack">Voltar</Button>
        <PermissionGate permission="leads.manage">
          <Button v-if="lead" variant="secondary" @click="goEdit">Editar</Button>
        </PermissionGate>
        <PermissionGate v-if="lead && !isConverted" permission="leads.manage">
          <Button @click="convertOpen = true">Agendar</Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="isError" variant="danger" title="Não foi possível carregar">
      O lead pode ter sido removido ou você não tem permissão.
    </Banner>

    <SurfaceCard v-else-if="isPending">
      <div class="flex flex-col gap-3">
        <Skeleton class="h-6 w-40" />
        <Skeleton class="h-5 w-56" />
        <Skeleton class="h-5 w-32" />
      </div>
    </SurfaceCard>

    <template v-else-if="lead">
      <Banner v-if="isConverted" variant="success" title="Virou paciente">
        {{ lead.client?.name ?? 'Paciente' }} · desde {{ formatDateTime(lead.converted_at) }}
      </Banner>

      <SurfaceCard>
        <dl class="flex flex-col gap-4">
          <div>
            <dt class="text-[13px] text-muted">Status</dt>
            <dd class="mt-1 flex flex-wrap items-center gap-2">
              <Badge :variant="lead.status?.color ?? 'muted'">{{ lead.status?.name ?? '—' }}</Badge>
              <PermissionGate v-if="!isConverted" permission="leads.manage">
                <Button variant="ghost" @click="statusOpen = true">Mudar status</Button>
              </PermissionGate>
            </dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Telefone</dt>
            <dd class="mt-0.5 text-[15px] text-title">{{ formatPhoneBR(lead.phone) || lead.phone }}</dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Origem</dt>
            <dd class="mt-0.5 text-[15px] text-title">{{ lead.client_origin?.name ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Cadastrado por</dt>
            <dd class="mt-0.5 text-[15px] text-title">
              {{ lead.created_by?.name ?? '—' }} · {{ formatDateTime(lead.created_at) }}
            </dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Observação</dt>
            <dd class="mt-0.5 whitespace-pre-wrap text-[15px] text-title">{{ lead.notes || '—' }}</dd>
          </div>
          <div v-if="isConverted">
            <dt class="text-[13px] text-muted">Paciente</dt>
            <dd class="mt-1">
              <Button variant="secondary" @click="openClient">
                Abrir {{ lead.client?.name ?? 'paciente' }}
              </Button>
            </dd>
          </div>
        </dl>
      </SurfaceCard>

      <SurfaceCard v-if="lead.activities?.length">
        <h2 class="mb-3">Histórico</h2>
        <LeadHistory :activities="lead.activities" />
      </SurfaceCard>

      <PermissionGate v-if="!isConverted" permission="leads.manage">
        <div>
          <Button variant="destructive" @click="deleteOpen = true">Excluir lead</Button>
        </div>
      </PermissionGate>

      <LeadStatusDialog v-model:open="statusOpen" :lead="lead" :statuses="statuses" />
      <LeadConvertDialog v-model:open="convertOpen" :lead="lead" @converted="onConverted" />
      <ConfirmDialog
        v-model:open="deleteOpen"
        title="Excluir este lead?"
        description="O lead e o histórico dele serão removidos. Esta ação não pode ser desfeita."
        confirm-label="Excluir"
        @confirm="deleteMutation.mutate()"
      />
    </template>
  </div>
</template>
