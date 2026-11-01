<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import Badge from '@/components/ui/Badge.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import AppDialog from '@/components/ui/AppDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Textarea from '@/components/ui/Textarea.vue'
import LeadConvertDialog from '@/features/leads/LeadConvertDialog.vue'
import LeadHistory from '@/features/leads/LeadHistory.vue'
import LeadStatusDialog from '@/features/leads/LeadStatusDialog.vue'
import { addLeadComment, cancelLead, getLead, logLeadWhatsApp } from '@/features/leads/api'
import LeadFunnelCard from '@/features/leads/LeadFunnelCard.vue'
import { useWhatsAppLinks } from '@/features/settings/useWhatsAppLinks'
import { openWhatsApp } from '@/lib/whatsapp'
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
const cancelOpen = ref(false)
const cancelReason = ref('')
const comment = ref('')
const commentError = ref('')
const idRef = computed(() => props.leadId)

const {
  data: lead,
  isPending,
  isError,
} = useQuery({
  queryKey: ['leads', 'detail', idRef],
  queryFn: () => getLead(idRef.value),
})

const isConverted = computed(() => Boolean(lead.value?.client_id))
const isCancelled = computed(() => Boolean(lead.value?.cancelled_at))
// "Virou paciente" and "Cancelado" are final: no status change, edit or conversion.
const isFinal = computed(() => isConverted.value || isCancelled.value)

const cancelMutation = useMutation({
  mutationFn: () => cancelLead(props.leadId, { reason: cancelReason.value.trim() || null }),
  onSuccess: async () => {
    toast.success('Lead cancelado')
    cancelOpen.value = false
    cancelReason.value = ''
    await queryClient.invalidateQueries({ queryKey: ['leads'] })
  },
  onError: (error) => {
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível cancelar.')
  },
})

const commentMutation = useMutation({
  mutationFn: () => addLeadComment(props.leadId, { body: comment.value.trim() }),
  onSuccess: async () => {
    comment.value = ''
    commentError.value = ''
    toast.success('Comentário adicionado')
    await queryClient.invalidateQueries({ queryKey: ['leads'] })
    await queryClient.invalidateQueries({ queryKey: ['clients'] })
  },
  onError: (error) => {
    if (error instanceof ApiError && error.status === 422) {
      commentError.value = error.first('body') || error.message
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível comentar.')
  },
})

const { leadUrl } = useWhatsAppLinks()

function callOnWhatsApp() {
  const url = lead.value ? leadUrl(lead.value) : null
  if (!url) {
    toast.error('Telefone inválido para WhatsApp.')
    return
  }
  openWhatsApp(url)
  void logLeadWhatsApp(props.leadId)
    .then(() => queryClient.invalidateQueries({ queryKey: ['leads'] }))
    .catch(() => undefined)
}

function submitComment() {
  if (!comment.value.trim()) {
    commentError.value = 'Escreva o comentário.'
    return
  }
  commentMutation.mutate()
}

function goEdit() {
  void router.push({
    name: 'leads-edit',
    params: { id: String(props.leadId) },
  })
}

function goBack() {
  void router.push({ name: 'leads' })
}

function openClient() {
  if (lead.value?.client_id) {
    void router.push({
      name: 'clients-show',
      params: { id: String(lead.value.client_id) },
    })
  }
}

function onConverted(result: LeadConvertResult) {
  void router.push({
    name: 'appointments-show',
    params: { id: String(result.appointment.id) },
  })
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader stack-actions :title="lead?.name ?? 'Lead'">
      <template #actions-negative>
        <Button mobile-block variant="ghost" @click="goBack">Voltar</Button>
      </template>
      <template #actions>
        <PermissionGate v-if="lead?.phone" permission="leads.manage">
          <Button mobile-block variant="secondary" @click="callOnWhatsApp">
            Chamar no WhatsApp
          </Button>
        </PermissionGate>
        <PermissionGate v-if="lead && !isCancelled" permission="leads.manage">
          <Button mobile-block variant="secondary" @click="goEdit">Editar</Button>
        </PermissionGate>
        <PermissionGate v-if="lead && !isFinal" permission="leads.manage">
          <Button mobile-block @click="convertOpen = true">Agendar</Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="isError" variant="danger" title="Não foi possível carregar">
      Você não tem permissão ou o lead não existe.
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
        {{ lead.client?.name ?? 'Paciente' }} · desde
        {{ formatDateTime(lead.converted_at) }}
      </Banner>
      <Banner v-else-if="isCancelled" variant="warning" title="Lead cancelado">
        Cancelado em {{ formatDateTime(lead.cancelled_at) }}. É definitivo: fica no histórico e nas
        métricas, mas não muda mais de status.
      </Banner>

      <SurfaceCard>
        <dl class="flex flex-col gap-4">
          <div>
            <dt class="text-[13px] text-muted">Status</dt>
            <dd class="mt-1 flex flex-wrap items-center gap-2">
              <Badge :variant="lead.status?.color ?? 'muted'">{{ lead.status?.name ?? '—' }}</Badge>
              <PermissionGate v-if="!isFinal" permission="leads.manage">
                <Button variant="ghost" @click="statusOpen = true">Mudar status</Button>
              </PermissionGate>
            </dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Telefone</dt>
            <dd class="mt-0.5 text-[15px] text-title">
              <template v-if="lead.phone">{{ formatPhoneBR(lead.phone) || lead.phone }}</template>
              <span v-else class="text-muted">Sem telefone. Para agendar, peça o número.</span>
            </dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Origem</dt>
            <dd class="mt-0.5 text-[15px] text-title">
              {{ lead.client_origin?.name ?? '—' }}
            </dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Cadastrado por</dt>
            <dd class="mt-0.5 text-[15px] text-title">
              {{ lead.created_by?.name ?? '—' }} ·
              {{ formatDateTime(lead.created_at) }}
            </dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Observação</dt>
            <dd class="mt-0.5 whitespace-pre-wrap text-[15px] text-title">
              {{ lead.notes || '—' }}
            </dd>
          </div>
          <div v-if="isConverted">
            <dt class="text-[13px] text-muted">Paciente</dt>
            <dd class="mt-1">
              <Button mobile-block variant="secondary" @click="openClient">
                Abrir {{ lead.client?.name ?? 'paciente' }}
              </Button>
            </dd>
          </div>
        </dl>
      </SurfaceCard>

      <LeadFunnelCard :lead="lead" />

      <SurfaceCard>
        <h2 class="mb-3">Histórico e comentários</h2>
        <PermissionGate permission="leads.manage">
          <form class="mb-4 flex flex-col gap-2" @submit.prevent="submitComment">
            <FormField
              label="Novo comentário"
              hint="Alinhamentos e conversas com o lead. Comentários ficam no histórico; só o mais recente pode ser editado."
              :error="commentError"
              html-for="lead-comment"
            >
              <template #default="{ invalid }">
                <Textarea
                  id="lead-comment"
                  v-model="comment"
                  :rows="3"
                  :invalid="invalid"
                  placeholder="Ex.: falei por WhatsApp, prefere horário à tarde"
                />
              </template>
            </FormField>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              <Button
                mobile-block
                type="submit"
                variant="secondary"
                :loading="commentMutation.isPending.value"
                :disabled="!comment.trim()"
              >
                Comentar
              </Button>
            </div>
          </form>
        </PermissionGate>
        <LeadHistory
          v-if="lead.activities?.length"
          :activities="lead.activities"
          :lead-id="lead.id"
        />
      </SurfaceCard>

      <PermissionGate v-if="!isFinal" permission="leads.manage">
        <div>
          <Button mobile-block variant="destructive" @click="cancelOpen = true"
            >Cancelar lead</Button
          >
        </div>
      </PermissionGate>

      <LeadStatusDialog v-model:open="statusOpen" :lead="lead" :statuses="statuses" />
      <LeadConvertDialog v-model:open="convertOpen" :lead="lead" @converted="onConverted" />
      <AppDialog
      size="md"
        v-model:open="cancelOpen"
        stack-footer
        title="Cancelar este lead?"
        description="É definitivo: o lead fica na lista, no histórico e nas métricas, mas não muda mais de status nem vira paciente. O telefone fica livre para um novo lead."
      >
        <FormField label="Motivo (opcional)" html-for="lead-cancel-reason">
          <Textarea id="lead-cancel-reason" v-model="cancelReason" :rows="3" />
        </FormField>
        <template #footer-negative>
          <Button mobile-block variant="ghost" type="button" @click="cancelOpen = false"
            >Voltar</Button
          >
        </template>
        <template #footer>
          <Button
            mobile-block
            variant="destructive"
            type="button"
            :loading="cancelMutation.isPending.value"
            @click="cancelMutation.mutate()"
          >
            Cancelar lead
          </Button>
        </template>
      </AppDialog>
    </template>
  </div>
</template>
