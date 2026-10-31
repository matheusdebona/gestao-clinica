<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
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
import Switch from '@/components/ui/Switch.vue'
import {
  createLeadStatus,
  deleteLeadStatus,
  listLeadStatuses,
  updateLeadStatus,
} from '@/features/leads/api'
import { LEAD_STATUS_COLOR_OPTIONS } from '@/features/leads/labels'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { LeadStatus, LeadStatusColor, LeadStatusPayload } from '@/types/lead'
import { ApiError } from '@/types/user'

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()
const queryClient = useQueryClient()
const canManage = computed(() => auth.can('lead_statuses.manage'))

const { data, isPending, isError } = useQuery({
  queryKey: ['lead-statuses', 'all'],
  queryFn: () => listLeadStatuses(),
  enabled: canManage,
})
const statuses = computed(() => data.value ?? [])

const dialogOpen = ref(false)
const confirmOpen = ref(false)
const editing = ref<LeadStatus | null>(null)
const form = ref({
  name: '',
  color: 'muted' as LeadStatusColor,
  sort_order: '',
  is_active: true,
  is_default: false,
})
const formErrors = ref<Record<string, string>>({})

function openNew() {
  editing.value = null
  const nextOrder = Math.max(0, ...statuses.value.map((status) => status.sort_order)) + 10
  form.value = {
    name: '',
    color: 'muted',
    sort_order: String(nextOrder),
    is_active: true,
    is_default: false,
  }
  formErrors.value = {}
  dialogOpen.value = true
}

function openEdit(status: LeadStatus) {
  editing.value = status
  form.value = {
    name: status.name,
    color: status.color,
    sort_order: String(status.sort_order),
    is_active: status.is_active,
    is_default: status.is_default,
  }
  formErrors.value = {}
  dialogOpen.value = true
}

function statusMeta(status: LeadStatus) {
  const bits = [`Ordem ${status.sort_order}`]
  if (status.leads_count !== undefined) {
    bits.push(`${status.leads_count} ${status.leads_count === 1 ? 'lead' : 'leads'}`)
  }
  if (status.is_default) {
    bits.push('Inicial')
  }
  if (status.system_key) {
    bits.push('Sistema (usado ao virar paciente)')
  }
  if (!status.is_active) {
    bits.push('Inativo')
  }
  return bits.join(' · ')
}

async function refresh() {
  await queryClient.invalidateQueries({ queryKey: ['lead-statuses'] })
}

function onApiError(error: unknown) {
  if (error instanceof ApiError && error.status === 422) {
    const mapped: Record<string, string> = {}
    for (const [field, messages] of Object.entries(error.errors)) {
      mapped[field] = messages[0] ?? error.message
    }
    formErrors.value = Object.keys(mapped).length ? mapped : { form: error.message }
    return
  }
  toast.error(error instanceof ApiError ? error.message : 'Não foi possível salvar.')
}

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: () => {
    const order = form.value.sort_order.trim()
    const payload: LeadStatusPayload = {
      name: form.value.name.trim(),
      color: form.value.color,
      is_active: form.value.is_active,
      is_default: form.value.is_default,
      ...(order ? { sort_order: Number(order) } : {}),
    }
    return editing.value ? updateLeadStatus(editing.value.id, payload) : createLeadStatus(payload)
  },
  onSuccess: async () => {
    toast.success(editing.value ? 'Status atualizado' : 'Status cadastrado')
    dialogOpen.value = false
    await refresh()
  },
  onError: onApiError,
})

const { mutate: remove } = useMutation({
  mutationFn: () => deleteLeadStatus(editing.value!.id),
  onSuccess: async (result) => {
    toast.success(result.deleted ? 'Status removido' : 'Status desativado (há leads com ele)')
    dialogOpen.value = false
    await refresh()
  },
  onError: (error) => {
    toast.error(
      error instanceof ApiError
        ? error.first('lead_status') || error.message
        : 'Não foi possível remover.',
    )
  },
})

function onSave() {
  if (!form.value.name.trim()) {
    formErrors.value = { name: 'Informe o nome.' }
    return
  }
  const order = form.value.sort_order.trim()
  if (order && (!Number.isInteger(Number(order)) || Number(order) < 0)) {
    formErrors.value = { sort_order: 'Informe um número inteiro.' }
    return
  }
  save()
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader
      stack-actions
      title="Status de leads"
      description="Etapas do funil de leads da clínica."
    >
      <template #actions>
        <Button mobile-block variant="ghost" @click="router.push({ name: 'settings' })"
          >Configurações</Button
        >
        <Button mobile-block v-if="canManage" @click="openNew">Novo</Button>
      </template>
    </PageHeader>

    <Banner v-if="!canManage" variant="danger" title="Sem permissão">
      Você não pode gerenciar status de leads.
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

      <SurfaceCard v-else-if="statuses.length === 0" :padding="false">
        <EmptyState title="Nenhum status ainda" description="Cadastre as etapas do funil." />
      </SurfaceCard>

      <SurfaceCard v-else :padding="false">
        <div class="divide-y divide-border-divider px-5 py-2">
          <ListCard
            v-for="status in statuses"
            :key="status.id"
            :title="status.name"
            :meta="statusMeta(status)"
            :badge="status.name"
            :badge-variant="status.color"
            @action="openEdit(status)"
          />
        </div>
      </SurfaceCard>
    </template>

    <AppDialog
      stack-footer
      v-model:open="dialogOpen"
      :title="editing ? 'Editar status' : 'Novo status'"
      :description="
        editing?.system_key
          ? 'Status do sistema: dá para renomear e trocar a cor, mas não desativar ou excluir.'
          : ''
      "
    >
      <div class="flex flex-col gap-4">
        <Banner v-if="formErrors.form" variant="danger">{{ formErrors.form }}</Banner>
        <FormField label="Nome" :error="formErrors.name" html-for="lead-status-name">
          <template #default="{ invalid }">
            <Input id="lead-status-name" v-model="form.name" :invalid="invalid" />
          </template>
        </FormField>
        <FormField label="Cor" :error="formErrors.color" html-for="lead-status-color">
          <template #default="{ invalid }">
            <div class="flex items-center gap-3">
              <div class="min-w-0 flex-1">
                <Select
                  id="lead-status-color"
                  :model-value="form.color"
                  :options="LEAD_STATUS_COLOR_OPTIONS"
                  :invalid="invalid"
                  @update:model-value="form.color = $event as LeadStatusColor"
                />
              </div>
              <Badge :variant="form.color">{{ form.name.trim() || 'Prévia' }}</Badge>
            </div>
          </template>
        </FormField>
        <FormField
          label="Ordem"
          hint="Menor aparece primeiro."
          :error="formErrors.sort_order"
          html-for="lead-status-order"
        >
          <template #default="{ invalid }">
            <Input
              id="lead-status-order"
              v-model="form.sort_order"
              type="number"
              :invalid="invalid"
            />
          </template>
        </FormField>
        <Switch
          v-model="form.is_active"
          label="Ativo"
          :disabled="Boolean(editing?.system_key) || Boolean(editing?.is_default)"
        />
        <Switch
          v-model="form.is_default"
          label="Status inicial de novos leads"
          :disabled="Boolean(editing?.system_key) || Boolean(editing?.is_default)"
        />
        <p v-if="formErrors.is_default || formErrors.is_active" class="text-[13px] text-danger">
          {{ formErrors.is_default || formErrors.is_active }}
        </p>
      </div>
      <template #footer>
        <Button
          mobile-block
          v-if="editing && !editing.system_key && !editing.is_default"
          variant="destructive"
          type="button"
          :disabled="saving"
          @click="confirmOpen = true"
        >
          Excluir
        </Button>
        <Button
          mobile-block
          variant="ghost"
          type="button"
          :disabled="saving"
          @click="dialogOpen = false"
          >Cancelar</Button
        >
        <Button mobile-block type="button" :loading="saving" @click="onSave">Salvar</Button>
      </template>
    </AppDialog>

    <ConfirmDialog
      stack-footer
      v-model:open="confirmOpen"
      title="Excluir este status?"
      description="Se houver leads com ele, o status é só desativado e os leads continuam com ele."
      confirm-label="Excluir"
      @confirm="remove()"
    />
  </div>
</template>
