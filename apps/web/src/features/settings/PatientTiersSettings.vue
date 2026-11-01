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
import MoneyInput from '@/components/ui/MoneyInput.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import {
  PATIENT_TIER_COLOR_OPTIONS,
  PATIENT_TIERS_KEY,
  createPatientTier,
  deletePatientTier,
  previewRange,
  restoreDefaultPatientTiers,
  tierBadgeVariant,
  updatePatientTier,
  usePatientTiers,
  type PatientTier,
  type PatientTierColor,
  type PatientTierPayload,
} from '@/features/clients/tiers'
import { formatBRL } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'

const auth = useAuthStore()
const toast = useToastStore()
const queryClient = useQueryClient()
const canEdit = computed(() => auth.can('clinics.branding') || auth.can('clinics.manage'))

const { tiers, isPending, isError } = usePatientTiers(canEdit)

const NO_COLOR = ''
const colorOptions = [{ value: NO_COLOR, label: 'Sem cor' }, ...PATIENT_TIER_COLOR_OPTIONS]

const dialogOpen = ref(false)
const confirmOpen = ref(false)
const editing = ref<PatientTier | null>(null)
const form = ref({ name: '', min_amount: '', color: NO_COLOR as PatientTierColor | '' })
const formErrors = ref<Record<string, string>>({})

const lowestNote = computed(() => {
  const lowest = tiers.value[0]
  if (!lowest || Number(lowest.min_amount) === 0) return ''
  return `Quem comprou menos de ${formatBRL(lowest.min_amount)} nos últimos 12 meses fica sem faixa.`
})

function openNew() {
  editing.value = null
  const top = tiers.value[tiers.value.length - 1]
  const suggested = top ? (Number(top.min_amount) * 2 || 1000).toFixed(2) : '0.00'
  form.value = { name: '', min_amount: suggested, color: NO_COLOR }
  formErrors.value = {}
  dialogOpen.value = true
}

function openEdit(tier: PatientTier) {
  editing.value = tier
  form.value = { name: tier.name, min_amount: tier.min_amount, color: tier.color ?? NO_COLOR }
  formErrors.value = {}
  dialogOpen.value = true
}

const others = computed(() => tiers.value.filter((tier) => tier.id !== editing.value?.id))
const preview = computed(() => previewRange(form.value.min_amount, others.value))

async function refresh() {
  await queryClient.invalidateQueries({ queryKey: PATIENT_TIERS_KEY })
  await queryClient.invalidateQueries({ queryKey: ['clients'] })
}

function onApiError(error: unknown) {
  if (error instanceof ApiError && error.status === 422) {
    formErrors.value = {
      name: error.first('name'),
      min_amount: error.first('min_amount'),
      color: error.first('color'),
    }
    return
  }
  toast.error(error instanceof ApiError ? error.message : 'Não foi possível salvar.')
}

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: () => {
    const payload: PatientTierPayload = {
      name: form.value.name.trim(),
      min_amount: form.value.min_amount || '0.00',
      color: form.value.color === NO_COLOR ? null : form.value.color,
    }
    return editing.value ? updatePatientTier(editing.value.id, payload) : createPatientTier(payload)
  },
  onSuccess: async () => {
    toast.success(editing.value ? 'Faixa atualizada' : 'Faixa cadastrada')
    dialogOpen.value = false
    await refresh()
  },
  onError: onApiError,
})

const { mutate: remove, isPending: removing } = useMutation({
  mutationFn: () => deletePatientTier(editing.value!.id),
  onSuccess: async () => {
    toast.success('Faixa removida')
    dialogOpen.value = false
    await refresh()
  },
  onError: () => toast.error('Não foi possível remover.'),
})

const { mutate: restore, isPending: restoring } = useMutation({
  mutationFn: restoreDefaultPatientTiers,
  onSuccess: async () => {
    toast.success('Faixas Bronze, Prata, Ouro e Diamante criadas')
    await refresh()
  },
  onError: (error) => toast.error(error instanceof ApiError ? error.message : 'Não foi possível criar as faixas.'),
})

function onSave() {
  const errors: Record<string, string> = {}
  if (!form.value.name.trim()) errors.name = 'Informe o nome da faixa.'
  if (form.value.min_amount !== '' && Number(form.value.min_amount) < 0) {
    errors.min_amount = 'O valor mínimo não pode ser negativo.'
  } else if (form.value.min_amount !== '' && !preview.value) {
    errors.min_amount = 'Já existe uma faixa começando nesse valor.'
  }
  formErrors.value = errors
  if (Object.keys(errors).length === 0) save()
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader
      hint="Conta o total das vendas confirmadas do paciente nos últimos 12 meses (valor efetivo). Rascunho e venda cancelada não entram. A faixa muda sozinha conforme o paciente compra ou as vendas saem da janela de 12 meses. A ordem segue o valor mínimo de cada faixa."
      :back="{ label: 'Configurações', to: { name: 'settings' } }"
      stack-actions
      title="Faixas de paciente"
      description="Classifique os pacientes pelo quanto compraram nos últimos 12 meses."
    >
      <template v-if="canEdit && tiers.length" #actions>
        <Button mobile-block @click="openNew">Nova faixa</Button>
      </template>
    </PageHeader>

    <Banner v-if="!canEdit" variant="danger" title="Sem permissão">
      Só a administração da clínica altera as faixas de paciente.
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
          title="Nenhuma faixa"
          description="Sem faixas, os pacientes aparecem sem selo e a lista mostra todos, sem filtro de faixa."
        >
          <template #action>
            <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row-reverse">
              <Button mobile-block @click="openNew">Nova faixa</Button>
              <Button mobile-block variant="secondary" :loading="restoring" @click="restore()">
                Usar Bronze, Prata, Ouro e Diamante
              </Button>
            </div>
          </template>
        </EmptyState>
      </SurfaceCard>

      <template v-else>
        <SurfaceCard :padding="false">
          <div class="divide-y divide-border-divider px-5 py-2">
            <ListCard
              v-for="tier in tiers"
              :key="tier.id"
              :title="tier.range_label"
              :badge="tier.name"
              :badge-variant="tierBadgeVariant(tier.color)"
              @action="openEdit(tier)"
            />
          </div>
        </SurfaceCard>
        <p v-if="lowestNote" class="px-1 text-[13px] text-muted">{{ lowestNote }}</p>
      </template>
    </template>

    <AppDialog
      size="lg"
      v-model:open="dialogOpen"
      stack-footer
      :title="editing ? 'Editar faixa' : 'Nova faixa'"
    >
      <div class="flex flex-col gap-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <FormField label="Nome" :error="formErrors.name" html-for="tier-name">
            <template #default="{ invalid }">
              <Input id="tier-name" v-model="form.name" maxlength="40" placeholder="Ex.: Prata" :invalid="invalid" />
            </template>
          </FormField>
          <FormField
            label="A partir de (R$)"
            hint="Total comprado nos últimos 12 meses. O fim da faixa é o início da próxima menos R$ 0,01."
            :error="formErrors.min_amount"
            html-for="tier-min"
          >
            <template #default="{ invalid }">
              <MoneyInput id="tier-min" v-model="form.min_amount" :invalid="invalid" />
            </template>
          </FormField>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <FormField label="Cor (opcional)" :error="formErrors.color" html-for="tier-color">
            <template #default="{ invalid }">
              <Select
                id="tier-color"
                :model-value="form.color"
                :options="colorOptions"
                :invalid="invalid"
                @update:model-value="form.color = $event as PatientTierColor | ''"
              />
            </template>
          </FormField>
          <FormField label="Como fica">
            <div class="flex min-h-11 flex-wrap items-center gap-2">
              <Badge :variant="tierBadgeVariant(form.color || null)">{{ form.name.trim() || 'Faixa' }}</Badge>
              <span class="text-[15px] text-body">{{ preview?.label ?? '—' }}</span>
            </div>
          </FormField>
        </div>
        <p v-if="preview?.belowLowest" class="text-[13px] text-muted">{{ preview.belowLowest }}</p>
      </div>
      <template #footer-negative>
        <Button
          v-if="editing"
          mobile-block
          variant="destructive"
          type="button"
          :disabled="saving || removing"
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
      title="Excluir esta faixa?"
      description="Os pacientes desta faixa passam para a faixa de baixo (ou ficam sem faixa). Dá para cadastrar de novo depois."
      confirm-label="Excluir"
      @confirm="remove()"
    />
  </div>
</template>
