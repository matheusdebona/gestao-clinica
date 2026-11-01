<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, reactive, ref } from 'vue'
import MonthSelect from '@/components/patterns/MonthSelect.vue'
import AppDialog from '@/components/ui/AppDialog.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import ListCard from '@/components/ui/ListCard.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Switch from '@/components/ui/Switch.vue'
import FinanceShortcuts from '@/features/finance/FinanceShortcuts.vue'
import {
  currentMonthKey,
  listRecurringExpenses,
  saveRecurringExpense,
} from '@/features/finance/api'
import { formatMonthLabel } from '@/features/finance/labels'
import { useFinanceCatalog } from '@/features/finance/useFinanceCatalog'
import { emptyToMoney, formatBRL } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { RecurringExpense } from '@/types/finance'
import { ApiError } from '@/types/user'

const auth = useAuthStore()
const toast = useToastStore()
const queryClient = useQueryClient()
const canView = computed(() => auth.can('finance.view'))
const canManage = computed(() => auth.can('finance.manage'))

const { data, isPending, isError } = useQuery({
  queryKey: ['finance', 'recurring'],
  queryFn: listRecurringExpenses,
  enabled: canView,
})
const catalog = useFinanceCatalog()

const monthlyTotal = computed(() =>
  (data.value ?? []).filter((r) => r.is_active).reduce((sum, r) => sum + Number(r.amount), 0),
)

function describe(r: RecurringExpense): string {
  const parts = [r.category_name ?? '']
  parts.push(`desde ${formatMonthLabel(r.starts_month)}`)
  if (r.ends_month) parts.push(`até ${formatMonthLabel(r.ends_month)}`)
  if (r.due_day) parts.push(`vence dia ${r.due_day}`)
  return parts.filter(Boolean).join(' · ')
}

const open = ref(false)
const editing = ref<RecurringExpense | null>(null)
const form = reactive({
  category: '',
  description: '',
  amount: '',
  due_day: '',
  starts_month: currentMonthKey(),
  has_end: false,
  ends_month: currentMonthKey(),
  supplier: '',
  origin: 'none',
  campaign: 'none',
  is_active: true,
})
const errors = reactive<Record<string, string>>({})
const formKind = computed(() => catalog.kindOf(form.category))

function clear() {
  Object.keys(errors).forEach((k) => delete errors[k])
}

function openNew() {
  editing.value = null
  Object.assign(form, {
    category: '',
    description: '',
    amount: '',
    due_day: '',
    starts_month: currentMonthKey(),
    has_end: false,
    ends_month: currentMonthKey(),
    supplier: '',
    origin: 'none',
    campaign: 'none',
    is_active: true,
  })
  clear()
  open.value = true
}

function openEdit(r: RecurringExpense) {
  if (!canManage.value) return
  editing.value = r
  Object.assign(form, {
    category: String(r.expense_category_id),
    description: r.description,
    amount: Number(r.amount).toFixed(2),
    due_day: r.due_day ? String(r.due_day) : '',
    starts_month: r.starts_month,
    has_end: Boolean(r.ends_month),
    ends_month: r.ends_month ?? currentMonthKey(),
    supplier: r.supplier ?? '',
    origin: r.client_origin_id ? String(r.client_origin_id) : 'none',
    campaign: r.campaign_id ? String(r.campaign_id) : 'none',
    is_active: r.is_active,
  })
  clear()
  open.value = true
}

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: () => {
    const marketing = formKind.value === 'marketing'
    return saveRecurringExpense(
      {
        expense_category_id: Number(form.category),
        description: form.description.trim(),
        amount: emptyToMoney(String(form.amount)) ?? '0',
        due_day: form.due_day ? Number(form.due_day) : null,
        starts_month: form.starts_month,
        ends_month: form.has_end ? form.ends_month : null,
        supplier: form.supplier.trim() || null,
        client_origin_id: marketing && form.origin !== 'none' ? Number(form.origin) : null,
        campaign_id: marketing && form.campaign !== 'none' ? Number(form.campaign) : null,
        is_active: form.is_active,
      },
      editing.value?.id,
    )
  },
  onSuccess: async () => {
    open.value = false
    await queryClient.invalidateQueries({ queryKey: ['finance'] })
    toast.success('Recorrência salva. Os meses desde o início já foram lançados.')
  },
  onError: (err) => {
    if (err instanceof ApiError) {
      for (const f of ['expense_category_id', 'description', 'amount', 'due_day', 'ends_month']) {
        const m = err.first(f)
        if (m) errors[f] = m
      }
      if (!Object.keys(errors).length) errors.description = err.message
    } else errors.description = 'Não foi possível salvar.'
  },
})

function submit() {
  clear()
  if (!form.category) errors.expense_category_id = 'Selecione a categoria.'
  if (!form.description.trim()) errors.description = 'Informe a descrição.'
  if (!(Number(emptyToMoney(String(form.amount)) ?? 0) > 0))
    errors.amount = 'Informe um valor maior que zero.'
  const day = Number(form.due_day)
  if (form.due_day && (!Number.isInteger(day) || day < 1 || day > 31))
    errors.due_day = 'Dia de 1 a 31.'
  if (form.has_end && form.ends_month < form.starts_month)
    errors.ends_month = 'O fim deve ser depois do início.'
  if (!Object.keys(errors).length) save()
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[960px] flex-col gap-6">
    <PageHeader
      title="Despesas recorrentes"
      description="Aluguel, salários, pró-labore, software… lançados automaticamente em todo mês de competência."
    >
      <template #actions>
        <Button v-if="canManage" mobile-block @click="openNew">Nova recorrente</Button>
      </template>
    </PageHeader>

    <Banner v-if="!canView" variant="danger" title="Sem permissão">
      Só a administração da clínica vê as despesas.
    </Banner>

    <template v-else>
      <FinanceShortcuts current="recurring" />

      <Banner v-if="isError" variant="danger" title="Não foi possível carregar">
        Tente de novo em instantes.
      </Banner>
      <SurfaceCard v-else-if="isPending">
        <Skeleton class="h-12" />
        <Skeleton class="mt-3 h-12" />
      </SurfaceCard>
      <SurfaceCard v-else>
        <div class="flex items-baseline justify-between gap-3">
          <h2>Ativas</h2>
          <span class="shrink-0 tabular-nums text-[15px] font-semibold text-title">
            {{ formatBRL(monthlyTotal) }}/mês
          </span>
        </div>
        <EmptyState
          v-if="(data ?? []).length === 0"
          title="Nenhuma recorrente"
          description="Cadastre as despesas que se repetem todo mês para o DRE já vir preenchido."
        >
        </EmptyState>
        <div v-else class="mt-2 divide-y divide-border-divider">
          <ListCard
            v-for="r in data"
            :key="r.id"
            :title="`${r.description} · ${formatBRL(r.amount)}`"
            :description="describe(r)"
            :badge="r.is_active ? '' : 'Inativa'"
            :show-chevron="canManage"
            @action="openEdit(r)"
          />
        </div>
      </SurfaceCard>
    </template>

    <AppDialog
      size="lg"
      v-model:open="open"
      :title="editing ? 'Editar recorrente' : 'Nova despesa recorrente'"
      description="Mudanças valem para os meses ainda não lançados; meses já lançados ficam como estão."
    >
      <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
        <FormField label="Categoria" :error="errors.expense_category_id" html-for="rec-category">
          <template #default="{ invalid }">
            <Select
              id="rec-category"
              v-model="form.category"
              :options="catalog.categoryOptions.value"
              placeholder="Selecionar categoria"
              :invalid="invalid"
            />
          </template>
        </FormField>
        <FormField label="Descrição" :error="errors.description" html-for="rec-description">
          <template #default="{ invalid }">
            <Input
              id="rec-description"
              v-model="form.description"
              type="text"
              placeholder="Ex.: Aluguel da sala"
              :invalid="invalid"
            />
          </template>
        </FormField>
        <FormField label="Valor mensal" :error="errors.amount" html-for="rec-amount">
          <template #default="{ invalid }">
            <MoneyInput id="rec-amount" v-model="form.amount" :invalid="invalid" />
          </template>
        </FormField>
        <FormField
          label="Dia do vencimento (opcional)"
          :error="errors.due_day"
          html-for="rec-day"
        >
          <template #default="{ invalid }">
            <Input
              id="rec-day"
              v-model="form.due_day"
              type="number"
              inputmode="numeric"
              :invalid="invalid"
            />
          </template>
        </FormField>
        <FormField label="Começa em" html-for="rec-start">
          <MonthSelect id="rec-start" v-model="form.starts_month" />
        </FormField>
        <Switch class="md:col-span-2" v-model="form.has_end" label="Tem data para terminar" />
        <FormField
          v-if="form.has_end"
          label="Termina em"
          :error="errors.ends_month"
          html-for="rec-end"
        >
          <MonthSelect id="rec-end" v-model="form.ends_month" :future="60" />
        </FormField>
        <FormField label="Fornecedor (opcional)" html-for="rec-supplier">
          <Input id="rec-supplier" v-model="form.supplier" type="text" />
        </FormField>
        <template v-if="formKind === 'marketing'">
          <FormField label="Origem" html-for="rec-origin">
            <Select
              id="rec-origin"
              v-model="form.origin"
              :options="catalog.originOptions.value"
              @update:model-value="form.campaign = 'none'"
            />
          </FormField>
          <FormField label="Campanha (opcional)" html-for="rec-campaign">
            <Select
              id="rec-campaign"
              v-model="form.campaign"
              :options="catalog.campaignOptions(form.origin)"
            />
          </FormField>
        </template>
        <Switch class="md:col-span-2" v-if="editing" v-model="form.is_active" label="Ativa" />
      </form>
      <template #footer-negative>
        <Button variant="ghost" type="button" @click="open = false">Cancelar</Button>
      </template>
      <template #footer>
        <Button type="button" :loading="saving" @click="submit">Salvar</Button>
      </template>
    </AppDialog>
  </div>
</template>
