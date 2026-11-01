<script setup lang="ts">
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { MessageCircle, MessageSquarePlus, RefreshCw } from '@lucide/vue'
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import AppDialog from '@/components/ui/AppDialog.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormField from '@/components/ui/FormField.vue'
import IconButton from '@/components/ui/IconButton.vue'
import ListCard from '@/components/ui/ListCard.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Textarea from '@/components/ui/Textarea.vue'
import type { DailyQueueItem } from '@/features/daily/api'
import LeadStatusDialog from '@/features/leads/LeadStatusDialog.vue'
import { addLeadComment, logLeadWhatsApp } from '@/features/leads/api'
import { nextQuickMilestone, quickMilestoneLabel } from '@/features/leads/milestones'
import { staleLabel } from '@/features/leads/stale'
import { useLeadCatalogs } from '@/features/leads/useLeadCatalogs'
import { useLeadMilestone } from '@/features/leads/useLeadMilestone'
import { useWhatsAppLinks } from '@/features/settings/useWhatsAppLinks'
import { formatPhoneBR } from '@/lib/masks'
import { openWhatsApp } from '@/lib/whatsapp'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'

defineProps<{ items: DailyQueueItem[]; count: number }>()

const router = useRouter()
const toast = useToastStore()
const queryClient = useQueryClient()
const { statuses } = useLeadCatalogs()
const { leadUrl } = useWhatsAppLinks()
const milestone = useLeadMilestone()

const statusOpen = ref(false)
const statusLead = ref<DailyQueueItem | null>(null)
const commentOpen = ref(false)
const commentLead = ref<DailyQueueItem | null>(null)
const comment = ref('')
const commentError = ref('')

function meta(item: DailyQueueItem): string {
  const bits = [formatPhoneBR(item.phone) || item.phone]
  if (item.origin) {
    bits.push(item.origin)
  }
  bits.push(item.reason === 'not_approached' ? 'Ainda não abordado' : `Sem contato há ${item.days_without_contact} dias`)
  return bits.join(' · ')
}

function callOnWhatsApp(item: DailyQueueItem) {
  const url = leadUrl(item)
  if (!url) {
    toast.error('Telefone inválido para WhatsApp.')
    return
  }
  openWhatsApp(url)
  void logLeadWhatsApp(item.id)
    .then(() => queryClient.invalidateQueries({ queryKey: ['leads'] }))
    .catch(() => undefined)
}

function openStatus(item: DailyQueueItem) {
  statusLead.value = item
  statusOpen.value = true
}

function openComment(item: DailyQueueItem) {
  commentLead.value = item
  comment.value = ''
  commentError.value = ''
  commentOpen.value = true
}

const { mutate: sendComment, isPending: commenting } = useMutation({
  mutationFn: () => addLeadComment(commentLead.value!.id, { body: comment.value.trim() }),
  onSuccess: async () => {
    toast.success('Comentário adicionado')
    commentOpen.value = false
    await queryClient.invalidateQueries({ queryKey: ['leads'] })
  },
  onError: (error) => {
    commentError.value = error instanceof ApiError ? error.first('body') || error.message : 'Não foi possível comentar.'
  },
})

function onComment() {
  if (!comment.value.trim()) {
    commentError.value = 'Escreva o comentário.'
    return
  }
  sendComment()
}
</script>

<template>
  <SurfaceCard :padding="false">
    <div class="px-5 pt-5">
      <h2>Fila de acompanhamento</h2>
      <p class="mt-1 text-[13px] text-muted">
        Leads em aberto que passaram de um prazo de acompanhamento ou ainda não foram abordados.
        <template v-if="count > items.length"> Mostrando {{ items.length }} de {{ count }}.</template>
      </p>
    </div>
    <EmptyState v-if="items.length === 0" title="Fila vazia" description="Nenhum lead esperando acompanhamento." />
    <div v-else class="divide-y divide-border-divider px-5 py-2">
      <div v-for="item in items" :key="item.id" class="flex flex-col">
        <div class="flex items-center gap-1">
          <div class="min-w-0 flex-1">
            <ListCard
              :title="item.name"
              :meta="meta(item)"
              :badge="item.status?.name ?? ''"
              :badge-variant="item.status?.color ?? 'muted'"
              @action="router.push({ name: 'leads-show', params: { id: String(item.id) } })"
            >
              <template v-if="item.tier" #status>
                <Badge :variant="item.tier.color">{{ staleLabel(item.days_without_contact) }}</Badge>
              </template>
            </ListCard>
          </div>
          <PermissionGate permission="leads.manage">
            <IconButton label="Chamar no WhatsApp" @click="callOnWhatsApp(item)">
              <MessageCircle class="size-4" :stroke-width="1.75" />
            </IconButton>
            <IconButton label="Comentar" @click="openComment(item)">
              <MessageSquarePlus class="size-4" :stroke-width="1.75" />
            </IconButton>
            <IconButton label="Mudar status" @click="openStatus(item)">
              <RefreshCw class="size-4" :stroke-width="1.75" />
            </IconButton>
          </PermissionGate>
        </div>
        <PermissionGate v-if="nextQuickMilestone(item)" permission="leads.manage">
          <div class="flex px-2 pb-3 sm:justify-end">
            <Button
              mobile-block
              variant="secondary"
              :loading="milestone.isPending.value && milestone.variables.value?.id === item.id"
              @click="milestone.mutate({ id: item.id, milestone: nextQuickMilestone(item)! })"
            >
              {{ quickMilestoneLabel(nextQuickMilestone(item)!) }}
            </Button>
          </div>
        </PermissionGate>
      </div>
    </div>

    <LeadStatusDialog v-model:open="statusOpen" :lead="statusLead" :statuses="statuses" />

    <AppDialog
      size="md" v-model:open="commentOpen" stack-footer title="Comentar" :description="commentLead?.name ?? ''">
      <FormField label="Comentário" html-for="daily-comment" :error="commentError">
        <template #default="{ invalid }">
          <Textarea id="daily-comment" v-model="comment" :invalid="invalid" placeholder="Ex.: liguei, pediu retorno sexta." />
        </template>
      </FormField>
      <template #footer-negative>
        <Button mobile-block variant="ghost" type="button" @click="commentOpen = false">Cancelar</Button>
      </template>
      <template #footer>
        <Button mobile-block type="button" :loading="commenting" @click="onComment">Comentar</Button>
      </template>
    </AppDialog>
  </SurfaceCard>
</template>
