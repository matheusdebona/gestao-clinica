<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, reactive, ref } from 'vue'
import MonthSelect from '@/components/patterns/MonthSelect.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import ListCard from '@/components/ui/ListCard.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SectionTitle from '@/components/ui/SectionTitle.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Textarea from '@/components/ui/Textarea.vue'
import {
  deleteTaxSetting,
  isValidPercent,
  listTaxSettings,
  percentToInput,
  saveTaxSetting,
  toPercent,
} from '@/features/finance/api'
import { formatMonthLabel, REGIME_LABELS, TAX_FIELDS_BY_REGIME } from '@/features/finance/labels'
import { formatPercent } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { TaxRegime, TaxSetting, TaxSettingPayload } from '@/types/finance'
import { ApiError } from '@/types/user'

const auth = useAuthStore()
const toast = useToastStore()
const queryClient = useQueryClient()
const canView = computed(() => auth.can('finance.view'))
const canManage = computed(() => auth.can('finance.manage'))

const { data, isPending, isError } = useQuery({
  queryKey: ['finance', 'tax-settings'],
  queryFn: listTaxSettings,
  enabled: canView,
})

function currentMonth(): string {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
}

const current = computed(() => {
  const month = currentMonth()
  return (data.value ?? []).find((s) => s.effective_month <= month) ?? null
})

type PercentField =
  | 'simples_rate_percent'
  | 'iss_percent'
  | 'pis_percent'
  | 'cofins_percent'
  | 'irpj_percent'
  | 'csll_percent'
  | 'other_percent'

const FIELD_LABELS: Record<PercentField, string> = {
  simples_rate_percent: 'Alíquota efetiva do Simples (%)',
  iss_percent: 'ISS (%)',
  pis_percent: 'PIS (%)',
  cofins_percent: 'COFINS (%)',
  irpj_percent: 'IRPJ efetivo sobre a receita (%)',
  csll_percent: 'CSLL efetiva sobre a receita (%)',
  other_percent: 'Outros impostos sobre a receita (%)',
}

const form = reactive({
  effective_month: currentMonth(),
  regime: 'simples' as TaxRegime,
  simples_rate_percent: '',
  iss_percent: '',
  pis_percent: '',
  cofins_percent: '',
  irpj_percent: '',
  csll_percent: '',
  other_percent: '',
  notes: '',
})
const errors = reactive<Record<string, string>>({})

const regimeOptions = Object.entries(REGIME_LABELS).map(([value, label]) => ({ value, label }))
const visibleFields = computed(() => TAX_FIELDS_BY_REGIME[form.regime] as PercentField[])

const previewRate = computed(() =>
  visibleFields.value.reduce((sum, field) => sum + Number(toPercent(form[field]) ?? 0), 0),
)

function load(setting: TaxSetting) {
  form.effective_month = setting.effective_month
  form.regime = setting.regime
  for (const field of Object.keys(FIELD_LABELS) as PercentField[]) {
    form[field] = percentToInput(setting[field])
  }
  form.notes = setting.notes ?? ''
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const { mutate, isPending: saving } = useMutation({
  mutationFn: () => {
    const body: TaxSettingPayload = {
      effective_month: form.effective_month,
      regime: form.regime,
      notes: form.notes.trim() || null,
    }
    for (const field of Object.keys(FIELD_LABELS) as PercentField[]) {
      body[field] = visibleFields.value.includes(field) ? toPercent(form[field]) : null
    }
    return saveTaxSetting(body)
  },
  onSuccess: async () => {
    await queryClient.invalidateQueries({ queryKey: ['finance'] })
    await queryClient.invalidateQueries({ queryKey: ['metrics'] })
    toast.success('Impostos salvos')
  },
  onError: (err) => {
    if (err instanceof ApiError) {
      for (const field of ['effective_month', 'regime', ...Object.keys(FIELD_LABELS)]) {
        const message = err.first(field)
        if (message) errors[field] = message
      }
      if (Object.keys(errors).length === 0) errors.regime = err.message
    } else {
      errors.regime = 'Não foi possível salvar.'
    }
  },
})

function submit() {
  Object.keys(errors).forEach((k) => delete errors[k])
  if (!/^\d{4}-\d{2}$/.test(form.effective_month)) {
    errors.effective_month = 'Informe o mês (AAAA-MM).'
  }
  for (const field of visibleFields.value) {
    if (!isValidPercent(form[field])) errors[field] = 'Percentual entre 0 e 100.'
  }
  if (form.regime === 'simples' && toPercent(form.simples_rate_percent) === null) {
    errors.simples_rate_percent = 'Informe a alíquota efetiva do Simples.'
  }
  if (Object.keys(errors).length === 0) mutate()
}

const toDelete = ref<TaxSetting | null>(null)
const deleteOpen = computed({
  get: () => toDelete.value !== null,
  set: (open) => {
    if (!open) toDelete.value = null
  },
})
const { mutate: remove } = useMutation({
  mutationFn: (id: number) => deleteTaxSetting(id),
  onSuccess: async () => {
    await queryClient.invalidateQueries({ queryKey: ['finance', 'tax-settings'] })
    await queryClient.invalidateQueries({ queryKey: ['metrics'] })
    toast.success('Vigência removida')
  },
})
</script>

<template>
  <div class="mx-auto flex w-full max-w-[960px] flex-col gap-6">
    <PageHeader
      :back="{ label: 'Configurações', to: { name: 'settings' } }"
      title="Impostos"
      description="Regime tributário e alíquotas sobre a receita, por mês de competência. Entram na margem de contribuição e no DRE."
    >
    </PageHeader>

    <Banner v-if="!canView" variant="danger" title="Sem permissão">
      Só a administração da clínica vê e altera os impostos.
    </Banner>

    <Banner v-else-if="isError" variant="danger" title="Não foi possível carregar">
      Tente de novo em instantes.
    </Banner>

    <SurfaceCard v-else-if="isPending">
      <Skeleton class="h-12" />
      <Skeleton class="mt-3 h-12" />
    </SurfaceCard>

    <template v-else>
      <Banner v-if="!current" variant="warning" title="Nenhum imposto configurado">
        Sem regime cadastrado, a margem considera imposto zero. Confirme a alíquota com o contador.
      </Banner>
      <Banner v-else variant="info" :title="`Vigente: ${REGIME_LABELS[current.regime]}`">
        Alíquota efetiva de {{ formatPercent(current.effective_rate_percent) }} sobre a receita,
        desde {{ formatMonthLabel(current.effective_month) }}.
      </Banner>

      <SurfaceCard v-if="canManage">
        <SectionTitle
          title="Nova vigência"
          hint="Vale a partir do mês escolhido até a próxima vigência. Salvar o mesmo mês substitui a anterior."
        />
        <form class="mt-4 flex flex-col gap-4" @submit.prevent="submit">
          <FormField
            label="A partir de (mês de competência)"
            :error="errors.effective_month"
            html-for="tax-month"
          >
            <template #default="{ invalid }">
              <MonthSelect id="tax-month" v-model="form.effective_month" :invalid="invalid" />
            </template>
          </FormField>
          <FormField label="Regime" :error="errors.regime" html-for="tax-regime">
            <template #default="{ invalid }">
              <Select
                id="tax-regime"
                v-model="form.regime"
                :options="regimeOptions"
                :invalid="invalid"
              />
            </template>
          </FormField>
          <p v-if="form.regime === 'mei'" class="text-[13px] text-muted">
            No MEI o DAS é um valor fixo por mês: lance-o como despesa da categoria Impostos.
          </p>
          <FormField
            v-for="field in visibleFields"
            :key="field"
            :label="FIELD_LABELS[field]"
            :error="errors[field]"
            :html-for="`tax-${field}`"
          >
            <template #default="{ invalid }">
              <Input
                :id="`tax-${field}`"
                v-model="form[field]"
                type="text"
                inputmode="decimal"
                placeholder="0"
                :invalid="invalid"
              />
            </template>
          </FormField>
          <FormField label="Observações" html-for="tax-notes">
            <Textarea id="tax-notes" v-model="form.notes" />
          </FormField>
          <p class="text-[15px] text-title">
            Alíquota efetiva: <strong>{{ formatPercent(previewRate) }}</strong>
          </p>
          <div class="flex sm:justify-end">
            <Button mobile-block type="submit" :loading="saving">Salvar vigência</Button>
          </div>
        </form>
      </SurfaceCard>

      <SurfaceCard v-if="(data ?? []).length > 0">
        <h2>Histórico</h2>
        <div class="mt-2 divide-y divide-border-divider">
          <div
            v-for="setting in data"
            :key="setting.id"
            class="flex flex-col gap-2 py-1 sm:flex-row sm:items-center"
          >
            <div class="min-w-0 flex-1">
              <ListCard
                :title="`${formatMonthLabel(setting.effective_month)} · ${REGIME_LABELS[setting.regime]}`"
                :description="`Alíquota efetiva ${formatPercent(setting.effective_rate_percent)}`"
                :show-chevron="canManage"
                @action="canManage && load(setting)"
              />
            </div>
            <Button v-if="canManage" mobile-block variant="ghost" @click="toDelete = setting">
              Remover
            </Button>
          </div>
        </div>
      </SurfaceCard>
    </template>

    <ConfirmDialog
      v-model:open="deleteOpen"
      title="Remover vigência?"
      description="Os meses cobertos passam a usar a vigência anterior (ou imposto zero)."
      confirm-label="Remover"
      @confirm="toDelete && remove(toDelete.id)"
    />
  </div>
</template>
