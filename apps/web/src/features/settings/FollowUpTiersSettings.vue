<script setup lang="ts">
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import AppDialog from '@/components/ui/AppDialog.vue'
import Badge from '@/components/ui/Badge.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import ListCard from '@/components/ui/ListCard.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import {
  createFollowUpTier,
  deleteFollowUpTier,
  updateFollowUpTier,
  type FollowUpTier,
  type FollowUpTierPayload,
} from '@/features/leads/api'
import { LEAD_STATUS_COLOR_OPTIONS } from '@/features/leads/labels'
import { staleLabel } from '@/features/leads/stale'
import { FOLLOW_UP_TIERS_KEY, useFollowUpTiers } from '@/features/leads/useFollowUpTiers'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { LeadStatusColor } from '@/types/lead'
import { ApiError } from '@/types/user'

const auth = useAuthStore()
const toast = useToastStore()
const queryClient = useQueryClient()
const canEdit = computed(() => auth.can('clinics.branding') || auth.can('clinics.manage'))

const { tiers, isPending, isError } = useFollowUpTiers()

const dialogOpen = ref(false)
const confirmOpen = ref(false)
const editing = ref<FollowUpTier | null>(null)
const form = ref({ days: '' as string | number, label: '', color: 'warning' as LeadStatusColor })
const formErrors = ref<Record<string, string>>({})

function openNew() {
  editing.value = null
  const next = tiers.value.length ? Math.max(...tiers.value.map((tier) => tier.days)) * 2 : 3
  form.value = { days: String(Math.min(next, 365)), label: '', color: 'warning' }
  formErrors.value = {}
  dialogOpen.value = true
}

function openEdit(tier: FollowUpTier) {
  editing.value = tier
  form.value = { days: String(tier.days), label: tier.label ?? '', color: tier.color }
  formErrors.value = {}
  dialogOpen.value = true
}

const previewDays = computed(() => {
  const days = Number(form.value.days)
  return Number.isInteger(days) && days > 0 ? days : 3
})

async function refresh() {
  await queryClient.invalidateQueries({ queryKey: FOLLOW_UP_TIERS_KEY })
  await queryClient.invalidateQueries({ queryKey: ['leads'] })
}

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: () => {
    const payload: FollowUpTierPayload = {
      days: Number(form.value.days),
      label: form.value.label.trim() || null,
      color: form.value.color,
    }
    return editing.value ? updateFollowUpTier(editing.value.id, payload) : createFollowUpTier(payload)
  },
  onSuccess: async () => {
    toast.success(editing.value ? 'Alerta atualizado' : 'Alerta cadastrado')
    dialogOpen.value = false
    await refresh()
  },
  onError: (error) => {
    if (error instanceof ApiError && error.status === 422) {
      formErrors.value = { days: error.first('days'), label: error.first('label'), color: error.first('color') }
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível salvar.')
  },
})

const { mutate: remove } = useMutation({
  mutationFn: () => deleteFollowUpTier(editing.value!.id),
  onSuccess: async () => {
    toast.success('Alerta removido')
    dialogOpen.value = false
    await refresh()
  },
  onError: () => toast.error('Não foi possível remover.'),
})

function onSave() {
  const days = Number(String(form.value.days).trim())
  if (!Number.isInteger(days) || days < 1 || days > 365) {
    formErrors.value = { days: 'Informe de 1 a 365 dias.' }
    return
  }
  save()
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader
      hint="Conta como contato: cadastro, comentário, mudança de status e WhatsApp. Leads que viraram paciente ou foram cancelados nunca ficam parados. Cada lead é avisado uma vez por prazo; um novo contato zera a contagem."
      :back="{ label: 'Configurações', to: { name: 'settings' } }"
      stack-actions
      title="Alertas de acompanhamento"
      description="Prazos sem nenhum contato para um lead em aberto aparecer como parado. Quem gerencia leads é avisado em Alertas quando um lead passa de cada prazo."
    >
      <template #actions>
        <Button v-if="canEdit" mobile-block @click="openNew">Novo prazo</Button>
      </template>
    </PageHeader>

    <Banner v-if="!canEdit" variant="danger" title="Sem permissão">
      Só a administração da clínica altera os alertas de acompanhamento.
    </Banner>

    <template v-else>
      <Banner v-if="isError" variant="danger" title="Não foi possível carregar">
        Tente de novo em instantes.
      </Banner>

      <SurfaceCard v-else-if="isPending" :padding="false">
        <div class="flex flex-col gap-3 p-5">
          <Skeleton class="h-12" />
          <Skeleton class="h-12" />
        </div>
      </SurfaceCard>

      <SurfaceCard v-else-if="tiers.length === 0" :padding="false">
        <EmptyState
          title="Nenhum prazo"
          description="Sem prazos, nenhum lead aparece como parado e ninguém é avisado."
        >
          <template #action>
            <Button mobile-block @click="openNew">Novo prazo</Button>
          </template>
        </EmptyState>
      </SurfaceCard>

      <SurfaceCard v-else :padding="false">
        <div class="divide-y divide-border-divider px-5 py-2">
          <ListCard
            v-for="tier in tiers"
            :key="tier.id"
            :title="tier.display_label"
            :meta="`A partir de ${tier.days === 1 ? '1 dia' : `${tier.days} dias`} sem contato`"
            :badge="staleLabel(tier.days)"
            :badge-variant="tier.color"
            @action="openEdit(tier)"
          />
        </div>
      </SurfaceCard>

    </template>

    <AppDialog
      size="md"
      v-model:open="dialogOpen"
      stack-footer
      :title="editing ? 'Editar prazo' : 'Novo prazo'"
    >
      <div class="flex flex-col gap-4">
        <FormField label="Dias sem contato" :error="formErrors.days" html-for="tier-days">
          <template #default="{ invalid }">
            <Input id="tier-days" v-model="form.days" type="number" :invalid="invalid" />
          </template>
        </FormField>
        <FormField
          label="Nome (opcional)"
          hint="Ex.: Esfriando. Vazio usa “Parados há N+ dias”."
          :error="formErrors.label"
          html-for="tier-label"
        >
          <template #default="{ invalid }">
            <Input id="tier-label" v-model="form.label" :invalid="invalid" />
          </template>
        </FormField>
        <FormField label="Cor" :error="formErrors.color" html-for="tier-color">
          <template #default="{ invalid }">
            <div class="flex items-center gap-3">
              <div class="min-w-0 flex-1">
                <Select
                  id="tier-color"
                  :model-value="form.color"
                  :options="LEAD_STATUS_COLOR_OPTIONS"
                  :invalid="invalid"
                  @update:model-value="form.color = $event as LeadStatusColor"
                />
              </div>
              <Badge :variant="form.color">{{ staleLabel(previewDays) }}</Badge>
            </div>
          </template>
        </FormField>
      </div>
      <template #footer-negative>
        <Button
          v-if="editing"
          mobile-block
          variant="destructive"
          type="button"
          :disabled="saving"
          @click="confirmOpen = true"
        >
          Excluir
        </Button>
        <Button mobile-block variant="ghost" type="button" :disabled="saving" @click="dialogOpen = false">
          Cancelar
        </Button>
      </template>
      <template #footer>
        <Button mobile-block type="button" :loading="saving" @click="onSave">Salvar</Button>
      </template>
    </AppDialog>

    <ConfirmDialog
      v-model:open="confirmOpen"
      stack-footer
      title="Excluir este prazo?"
      description="Os leads deixam de ser avisados por este prazo. Dá para cadastrar de novo depois."
      confirm-label="Excluir"
      @confirm="remove()"
    />
  </div>
</template>
