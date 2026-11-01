<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, reactive, ref } from 'vue'
import MonthSelect from '@/components/patterns/MonthSelect.vue'
import AppDialog from '@/components/ui/AppDialog.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import DatePicker from '@/components/ui/DatePicker.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import ListCard from '@/components/ui/ListCard.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Textarea from '@/components/ui/Textarea.vue'
import FinanceShortcuts from '@/features/finance/FinanceShortcuts.vue'
import { currentMonthKey, deleteExpense, listExpenses, saveExpense } from '@/features/finance/api'
import {
  EXPENSE_KIND_LABELS,
  EXPENSE_KIND_ORDER,
  formatMonthLabel,
} from '@/features/finance/labels'
import { useFinanceCatalog } from '@/features/finance/useFinanceCatalog'
import { emptyToMoney, formatBRL, formatIsoDate } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { Expense, ExpenseKind } from '@/types/finance'
import { ApiError } from '@/types/user'

const auth = useAuthStore()
const toast = useToastStore()
const queryClient = useQueryClient()
const canView = computed(() => auth.can('finance.view'))
const canManage = computed(() => auth.can('finance.manage'))

const month = ref(currentMonthKey())
const { data, isPending, isError } = useQuery({
  queryKey: ['finance', 'expenses', month],
  queryFn: () => listExpenses(month.value),
  enabled: canView,
})
const catalog = useFinanceCatalog()

const groups = computed(() => {
  const rows = data.value?.data ?? []
  return EXPENSE_KIND_ORDER.map((kind) => ({
    kind,
    label: EXPENSE_KIND_LABELS[kind],
    total: data.value?.meta.by_kind[kind] ?? '0.00',
    rows: rows.filter((r) => r.kind === kind),
  })).filter((g) => g.rows.length > 0)
})

function expenseDescription(e: Expense): string {
  const parts = [e.category_name ?? '']
  if (e.campaign_name || e.client_origin_name)
    parts.push(e.campaign_name ?? e.client_origin_name ?? '')
  if (e.paid_on) parts.push(`pago em ${formatIsoDate(e.paid_on)}`)
  else if (e.due_on) parts.push(`vence ${formatIsoDate(e.due_on)}`)
  return parts.filter(Boolean).join(' · ')
}

function expenseBadge(e: Expense): string {
  if (e.equipment_asset_id) return 'Depreciação'
  if (e.recurring_expense_id) return 'Recorrente'
  return ''
}

/* ---------- formulário ---------- */
const open = ref(false)
const editing = ref<Expense | null>(null)
const form = reactive({
  category: '',
  description: '',
  amount: '',
  competence_month: month.value,
  due_on: '',
  paid_on: '',
  supplier: '',
  origin: 'none',
  campaign: 'none',
  notes: '',
})
const errors = reactive<Record<string, string>>({})
const formKind = computed<ExpenseKind | null>(() => catalog.kindOf(form.category))

function clearErrors() {
  Object.keys(errors).forEach((k) => delete errors[k])
}

function openNew() {
  editing.value = null
  Object.assign(form, {
    category: '',
    description: '',
    amount: '',
    competence_month: month.value,
    due_on: '',
    paid_on: '',
    supplier: '',
    origin: 'none',
    campaign: 'none',
    notes: '',
  })
  clearErrors()
  open.value = true
}

function openEdit(e: Expense) {
  if (!canManage.value) return
  editing.value = e
  Object.assign(form, {
    category: String(e.expense_category_id),
    description: e.description,
    amount: Number(e.amount).toFixed(2),
    competence_month: e.competence_month,
    due_on: e.due_on ?? '',
    paid_on: e.paid_on ?? '',
    supplier: e.supplier ?? '',
    origin: e.client_origin_id ? String(e.client_origin_id) : 'none',
    campaign: e.campaign_id ? String(e.campaign_id) : 'none',
    notes: e.notes ?? '',
  })
  clearErrors()
  open.value = true
}

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: () => {
    const marketing = formKind.value === 'marketing'
    return saveExpense(
      {
        expense_category_id: Number(form.category),
        description: form.description.trim(),
        amount: emptyToMoney(String(form.amount)) ?? '0',
        competence_month: form.competence_month,
        due_on: form.due_on || null,
        paid_on: form.paid_on || null,
        supplier: form.supplier.trim() || null,
        client_origin_id: marketing && form.origin !== 'none' ? Number(form.origin) : null,
        campaign_id: marketing && form.campaign !== 'none' ? Number(form.campaign) : null,
        notes: form.notes.trim() || null,
      },
      editing.value?.id,
    )
  },
  onSuccess: async () => {
    open.value = false
    await queryClient.invalidateQueries({ queryKey: ['finance'] })
    toast.success('Despesa salva')
  },
  onError: (err) => {
    if (err instanceof ApiError) {
      for (const f of ['expense_category_id', 'description', 'amount', 'competence_month']) {
        const m = err.first(f)
        if (m) errors[f] = m
      }
      if (!Object.keys(errors).length) errors.description = err.message
    } else errors.description = 'Não foi possível salvar.'
  },
})

function submit() {
  clearErrors()
  if (!form.category) errors.expense_category_id = 'Selecione a categoria.'
  if (!form.description.trim()) errors.description = 'Informe a descrição.'
  const amount = Number(emptyToMoney(String(form.amount)) ?? 0)
  if (!(amount > 0)) errors.amount = 'Informe um valor maior que zero.'
  if (!Object.keys(errors).length) save()
}

const confirmDelete = ref(false)
const { mutate: remove } = useMutation({
  mutationFn: (id: number) => deleteExpense(id),
  onSuccess: async () => {
    open.value = false
    await queryClient.invalidateQueries({ queryKey: ['finance'] })
    toast.success('Despesa excluída')
  },
})
</script>

<template>
  <div class="mx-auto flex w-full max-w-[960px] flex-col gap-6">
    <PageHeader
      title="Despesas"
      description="Custos da clínica por mês de competência. Recorrentes e depreciação entram sozinhas todo mês."
    >
      <template #actions>
        <Button v-if="canManage" mobile-block @click="openNew">Nova despesa</Button>
      </template>
    </PageHeader>

    <Banner v-if="!canView" variant="danger" title="Sem permissão">
      Só a administração da clínica vê as despesas.
    </Banner>

    <template v-else>
      <FinanceShortcuts current="expenses" />

      <SurfaceCard>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h2>{{ formatMonthLabel(month) }}</h2>
            <p class="mt-1 text-[13px] text-muted">
              Total {{ formatBRL(data?.meta.total) }} · pago {{ formatBRL(data?.meta.paid) }}
            </p>
          </div>
          <div class="sm:w-48">
            <MonthSelect id="expenses-month" v-model="month" />
          </div>
        </div>
      </SurfaceCard>

      <Banner v-if="isError" variant="danger" title="Não foi possível carregar">
        Tente de novo em instantes.
      </Banner>

      <SurfaceCard v-else-if="isPending">
        <Skeleton class="h-12" />
        <Skeleton class="mt-3 h-12" />
      </SurfaceCard>

      <SurfaceCard v-else-if="groups.length === 0">
        <EmptyState
          title="Nenhuma despesa no mês"
          description="Lance aluguel, luz, salários, pró-labore, mídia… ou cadastre recorrentes para não precisar lançar todo mês."
        >
        </EmptyState>
      </SurfaceCard>

      <template v-else>
        <SurfaceCard v-for="group in groups" :key="group.kind">
          <div class="flex items-baseline justify-between gap-3">
            <h2>{{ group.label }}</h2>
            <span class="shrink-0 tabular-nums text-[15px] font-semibold text-title">
              {{ formatBRL(group.total) }}
            </span>
          </div>
          <div class="mt-2 divide-y divide-border-divider">
            <ListCard
              v-for="expense in group.rows"
              :key="expense.id"
              :title="`${expense.description} · ${formatBRL(expense.amount)}`"
              :description="expenseDescription(expense)"
              :badge="expenseBadge(expense)"
              badge-variant="purple"
              :show-chevron="canManage"
              @action="openEdit(expense)"
            />
          </div>
        </SurfaceCard>
      </template>
    </template>

    <AppDialog
      size="lg"
      v-model:open="open"
      :title="editing ? 'Editar despesa' : 'Nova despesa'"
      :description="
        editing?.recurring_expense_id
          ? 'Gerada pela recorrência. Alterar aqui muda só este mês.'
          : 'A competência define em qual mês do DRE a despesa entra.'
      "
    >
      <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
        <FormField label="Categoria" :error="errors.expense_category_id" html-for="exp-category">
          <template #default="{ invalid }">
            <Select
              id="exp-category"
              v-model="form.category"
              :options="catalog.categoryOptions.value"
              placeholder="Selecionar categoria"
              :invalid="invalid"
            />
          </template>
        </FormField>
        <FormField label="Descrição" :error="errors.description" html-for="exp-description">
          <template #default="{ invalid }">
            <Input
              id="exp-description"
              v-model="form.description"
              type="text"
              placeholder="Ex.: Conta de luz"
              :invalid="invalid"
            />
          </template>
        </FormField>
        <FormField label="Valor" :error="errors.amount" html-for="exp-amount">
          <template #default="{ invalid }">
            <MoneyInput id="exp-amount" v-model="form.amount" :invalid="invalid" />
          </template>
        </FormField>
        <FormField label="Competência" :error="errors.competence_month" html-for="exp-month">
          <MonthSelect id="exp-month" v-model="form.competence_month" />
        </FormField>
        <FormField label="Vencimento (opcional)" html-for="exp-due">
          <DatePicker id="exp-due" v-model="form.due_on" />
        </FormField>
        <FormField label="Pago em (opcional)" html-for="exp-paid">
          <DatePicker id="exp-paid" v-model="form.paid_on" />
        </FormField>
        <FormField label="Fornecedor (opcional)" html-for="exp-supplier">
          <Input id="exp-supplier" v-model="form.supplier" type="text" />
        </FormField>
        <template v-if="formKind === 'marketing'">
          <FormField
            label="Origem"
            hint="Liga o gasto de mídia à origem dos pacientes (para o CAC)."
            html-for="exp-origin"
          >
            <Select
              id="exp-origin"
              v-model="form.origin"
              :options="catalog.originOptions.value"
              @update:model-value="form.campaign = 'none'"
            />
          </FormField>
          <FormField label="Campanha (opcional)" html-for="exp-campaign">
            <Select
              id="exp-campaign"
              v-model="form.campaign"
              :options="catalog.campaignOptions(form.origin)"
            />
          </FormField>
        </template>
        <FormField class="md:col-span-2" label="Observações" html-for="exp-notes">
          <Textarea id="exp-notes" v-model="form.notes" />
        </FormField>
      </form>
      <template #footer-negative>
        <Button v-if="editing" variant="ghost" type="button" @click="confirmDelete = true">
          Excluir
        </Button>
        <Button variant="ghost" type="button" @click="open = false">Cancelar</Button>
      </template>
      <template #footer>
        <Button type="button" :loading="saving" @click="submit">Salvar</Button>
      </template>
    </AppDialog>

    <ConfirmDialog
      v-model:open="confirmDelete"
      title="Excluir despesa?"
      :description="
        editing?.recurring_expense_id || editing?.equipment_asset_id
          ? 'Este mês deixa de ter o lançamento e ele não é recriado.'
          : 'A despesa sai do mês e do DRE.'
      "
      confirm-label="Excluir"
      @confirm="editing && remove(editing.id)"
    />
  </div>
</template>
