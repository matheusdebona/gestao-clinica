<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, reactive, ref } from 'vue'
import MonthSelect from '@/components/patterns/MonthSelect.vue'
import AppDialog from '@/components/ui/AppDialog.vue'
import Badge from '@/components/ui/Badge.vue'
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
import SectionTitle from '@/components/ui/SectionTitle.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import GoalRemunerationsCard from '@/features/finance/GoalRemunerationsCard.vue'
import RevenueRemunerationsCard from '@/features/finance/RevenueRemunerationsCard.vue'
import {
  isValidPercent,
  listCommissionRules,
  listCommissions,
  markCommissionsPaid,
  percentToInput,
  rebuildCommissions,
  saveCommissionRule,
  setCommissionRuleActive,
  toPercent,
} from '@/features/finance/api'
import {
  entrySubtitle,
  entryTitle,
  originBreakdown,
  sortEntries,
} from '@/features/finance/goalRemuneration'
import { emptyToMoney, formatBRL, formatIsoDate, formatPercent } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { CommissionRole, CommissionRuleRow } from '@/types/finance'
import { ApiError } from '@/types/user'

const auth = useAuthStore()
const toast = useToastStore()
const queryClient = useQueryClient()
const canView = computed(() => auth.can('finance.view'))
const canManage = computed(() => auth.can('finance.manage'))

function currentMonth(): string {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
}
const month = ref(currentMonth())

const rulesQuery = useQuery({
  queryKey: ['finance', 'commission-rules'],
  queryFn: listCommissionRules,
  enabled: canView,
})
const entriesQuery = useQuery({
  queryKey: ['finance', 'commissions', month],
  queryFn: () => listCommissions(month.value),
  enabled: canView,
})

const ROLE_LABELS: Record<CommissionRole, string> = {
  seller: 'Vendedor',
  professional: 'Profissional',
}

function ruleTitle(rule: CommissionRuleRow): string {
  return `${ROLE_LABELS[rule.role]} · ${rule.user_name ?? 'Todos'}`
}

function ruleDescription(rule: CommissionRuleRow): string {
  return `${rule.protocol_name ?? 'Geral'} · ${ruleAmount(rule)}`
}

function ruleAmount(rule: CommissionRuleRow): string {
  const unit = rule.role === 'seller' ? 'por venda' : 'por sessão'
  if (rule.calc_type === 'fixed') return `${formatBRL(rule.value)} ${unit}`
  const base =
    rule.role === 'seller'
      ? rule.base === 'net'
        ? 'do valor líquido da venda'
        : 'do valor da venda'
      : rule.base === 'net'
        ? 'do valor líquido executado na sessão'
        : 'do valor executado na sessão'
  return `${formatPercent(rule.value)} ${base}`
}

/* ---------- regra ---------- */
const dialogOpen = ref(false)
const editing = ref<CommissionRuleRow | null>(null)
const form = reactive({
  role: 'seller' as CommissionRole,
  user: 'all',
  protocol: 'all',
  calc_type: 'percent' as 'percent' | 'fixed',
  value: '',
  base: 'gross' as 'gross' | 'net',
})
const errors = reactive<Record<string, string>>({})

const roleOptions = [
  { value: 'seller', label: 'Vendedor (quem vendeu)' },
  { value: 'professional', label: 'Profissional (quem realizou a sessão)' },
]
const typeOptions = [
  { value: 'percent', label: 'Percentual' },
  { value: 'fixed', label: 'Valor fixo' },
]
const baseOptions = computed(() => [
  {
    value: 'gross',
    label: form.role === 'seller' ? 'Valor da venda' : 'Valor executado na sessão',
  },
  { value: 'net', label: 'Valor líquido (sem taxas de cartão e impostos)' },
])
const userOptions = computed(() => {
  const roleName = form.role === 'seller' ? 'seller' : 'professional'
  const users = (rulesQuery.data.value?.meta.users ?? []).filter(
    (u) => u.roles.includes(roleName) || u.roles.includes('admin') || String(u.id) === form.user,
  )
  return [
    { value: 'all', label: 'Todos' },
    ...users.map((u) => ({ value: String(u.id), label: u.name })),
  ]
})
const protocolOptions = computed(() => [
  { value: 'all', label: 'Todos os protocolos (geral)' },
  ...(rulesQuery.data.value?.meta.protocols ?? []).map((p) => ({
    value: String(p.id),
    label: p.name,
  })),
])

function resetErrors() {
  Object.keys(errors).forEach((k) => delete errors[k])
}

function openNew() {
  editing.value = null
  Object.assign(form, {
    role: 'seller',
    user: 'all',
    protocol: 'all',
    calc_type: 'percent',
    value: '',
    base: 'gross',
  })
  resetErrors()
  dialogOpen.value = true
}

function openEdit(rule: CommissionRuleRow) {
  if (!canManage.value) return
  editing.value = rule
  Object.assign(form, {
    role: rule.role,
    user: rule.user_id ? String(rule.user_id) : 'all',
    protocol: rule.protocol_id ? String(rule.protocol_id) : 'all',
    calc_type: rule.calc_type,
    value:
      rule.calc_type === 'percent' ? percentToInput(rule.value) : Number(rule.value).toFixed(2),
    base: rule.base,
  })
  resetErrors()
  dialogOpen.value = true
}

function parsedValue(): string | null {
  return form.calc_type === 'percent' ? toPercent(form.value) : emptyToMoney(String(form.value))
}

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: () =>
    saveCommissionRule(
      {
        role: form.role,
        user_id: form.user === 'all' ? null : Number(form.user),
        protocol_id: form.protocol === 'all' ? null : Number(form.protocol),
        calc_type: form.calc_type,
        value: parsedValue() ?? '0',
        base: form.base,
        is_active: true,
      },
      editing.value?.id,
    ),
  onSuccess: async () => {
    dialogOpen.value = false
    await queryClient.invalidateQueries({ queryKey: ['finance', 'commission-rules'] })
    toast.success('Regra salva. Use "Recalcular mês" para aplicar a lançamentos já gerados.')
  },
  onError: (err) => {
    errors.value =
      err instanceof ApiError ? err.first('value') || err.message : 'Não foi possível salvar.'
  },
})

function submit() {
  resetErrors()
  const value = parsedValue()
  if (value === null || Number(value) <= 0) {
    errors.value = 'Informe um valor maior que zero.'
  } else if (form.calc_type === 'percent' && !isValidPercent(form.value)) {
    errors.value = 'Percentual entre 0 e 100.'
  }
  if (Object.keys(errors).length === 0) save()
}

const { mutate: toggle, isPending: toggling } = useMutation({
  mutationFn: (rule: CommissionRuleRow) => setCommissionRuleActive(rule.id, !rule.is_active),
  onSuccess: async () => {
    dialogOpen.value = false
    await queryClient.invalidateQueries({ queryKey: ['finance', 'commission-rules'] })
  },
})

/* ---------- lançamentos ---------- */
const entries = computed(() => entriesQuery.data.value?.data ?? [])
const meta = computed(() => entriesQuery.data.value?.meta)

function entriesOf(userId: number) {
  return entries.value.filter((e) => e.user_id === userId)
}

const { mutate: rebuild, isPending: rebuilding } = useMutation({
  mutationFn: () => rebuildCommissions(month.value),
  onSuccess: async () => {
    await queryClient.invalidateQueries({ queryKey: ['finance', 'commissions'] })
    await queryClient.invalidateQueries({ queryKey: ['metrics'] })
    toast.success('Comissões do mês recalculadas')
  },
  onError: () => toast.error('Não foi possível recalcular.'),
})

const payingUser = ref<number | null>(null)
const { mutate: pay } = useMutation({
  mutationFn: ({ ids, paid }: { ids: number[]; paid: boolean; userId: number }) =>
    markCommissionsPaid(ids, paid),
  onMutate: ({ userId }) => {
    payingUser.value = userId
  },
  onSuccess: async (_, { paid }) => {
    await queryClient.invalidateQueries({ queryKey: ['finance', 'commissions'] })
    toast.success(paid ? 'Marcado como pago' : 'Pagamento desfeito')
  },
  onSettled: () => {
    payingUser.value = null
  },
})

function payUser(userId: number) {
  const ids = entriesOf(userId)
    .filter((e) => e.status === 'pending')
    .map((e) => e.id)
  if (ids.length) pay({ ids, paid: true, userId })
}

function unpayUser(userId: number) {
  const ids = entriesOf(userId)
    .filter((e) => e.status === 'paid')
    .map((e) => e.id)
  if (ids.length) pay({ ids, paid: false, userId })
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[960px] flex-col gap-6">
    <PageHeader
      :back="{ label: 'Configurações', to: { name: 'settings' } }"
      title="Comissões"
      description="Comissões de vendas e sessões e a remuneração por meta e faturamento."
      hint="Regras para vendedores (por venda) e profissionais (por sessão realizada), mais o valor por meta (ex.: por novo agendamento) e as faixas por faturamento da clínica. Os lançamentos são gerados ao confirmar vendas, concluir sessões e marcar as metas. Todas as comissões entram como custo na margem de contribuição e na DRE."
    >
      <template #actions>
        <Button v-if="canManage" mobile-block @click="openNew">Nova regra</Button>
      </template>
    </PageHeader>

    <Banner v-if="!canView" variant="danger" title="Sem permissão">
      Só a administração da clínica vê comissões.
    </Banner>

    <template v-else>
      <SurfaceCard>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h2>Lançamentos do mês</h2>
            <p class="mt-1 text-[13px] text-muted">
              Por mês de competência da venda, sessão, meta ou faturamento.
            </p>
          </div>
          <div class="sm:w-48">
            <MonthSelect id="commission-month" v-model="month" :future="0" />
          </div>
        </div>

        <div v-if="entriesQuery.isPending.value" class="mt-4">
          <Skeleton class="h-14" />
          <Skeleton class="mt-3 h-14" />
        </div>
        <Banner
          v-else-if="entriesQuery.isError.value"
          class="mt-4"
          variant="danger"
          title="Não foi possível carregar"
        >
          Tente de novo em instantes.
        </Banner>
        <template v-else-if="meta">
          <div class="mt-4 grid grid-cols-2 gap-3">
            <div class="glass-chip rounded-[14px] px-3.5 py-3">
              <p class="text-[13px] text-muted">Total do mês</p>
              <p class="text-[17px] font-semibold tabular-nums text-title">
                {{ formatBRL(meta.total) }}
              </p>
            </div>
            <div class="glass-chip rounded-[14px] px-3.5 py-3">
              <p class="text-[13px] text-muted">A pagar</p>
              <p class="text-[17px] font-semibold tabular-nums text-title">
                {{ formatBRL(meta.pending) }}
              </p>
            </div>
          </div>
          <ul
            v-if="originBreakdown(meta.by_origin).length"
            class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[13px] text-muted"
            aria-label="Total por origem"
          >
            <li v-for="o in originBreakdown(meta.by_origin)" :key="o.key">
              {{ o.label }}
              <span class="font-semibold tabular-nums text-title">{{ formatBRL(o.amount) }}</span>
            </li>
          </ul>

          <EmptyState
            v-if="meta.by_user.length === 0"
            title="Nenhuma comissão no mês"
            description="Cadastre regras, a remuneração por meta ou por faturamento. Para meses anteriores, use Recalcular mês."
          />

          <div v-else class="mt-4 flex flex-col divide-y divide-border-divider">
            <div
              v-for="person in meta.by_user"
              :key="person.user_id"
              class="flex flex-col gap-2 py-4 first:pt-0"
            >
              <div class="flex items-baseline justify-between gap-3">
                <h3 class="min-w-0 truncate">{{ person.user_name ?? 'Usuário' }}</h3>
                <span class="shrink-0 tabular-nums text-[15px] font-semibold text-title">
                  {{ formatBRL(person.total) }}
                </span>
              </div>
              <p class="text-[13px] text-muted">
                {{ person.count }} {{ person.count === 1 ? 'lançamento' : 'lançamentos' }} · a pagar
                {{ formatBRL(person.pending) }} · pago {{ formatBRL(person.paid) }}
              </p>
              <p v-if="originBreakdown(person.by_origin).length > 1" class="text-[13px] text-muted">
                <template v-for="(o, i) in originBreakdown(person.by_origin)" :key="o.key">
                  <template v-if="i > 0"> · </template>{{ o.label }}
                  <span class="tabular-nums text-body">{{ formatBRL(o.amount) }}</span>
                </template>
              </p>
              <ul class="flex flex-col gap-2">
                <li
                  v-for="entry in sortEntries(entriesOf(person.user_id))"
                  :key="entry.id"
                  class="flex items-start justify-between gap-3 text-[13px]"
                >
                  <span class="min-w-0">
                    <span class="block truncate text-body">{{ entryTitle(entry) }}</span>
                    <span
                      class="block text-muted"
                      :class="entry.goal_kind === 'tier' ? 'break-words' : 'truncate'"
                    >
                      {{ entrySubtitle(entry, formatIsoDate(entry.occurred_at?.slice(0, 10))) }}
                    </span>
                  </span>
                  <span class="flex shrink-0 items-center gap-2 tabular-nums text-title">
                    {{ formatBRL(entry.amount) }}
                    <Badge v-if="entry.status === 'paid'" variant="success">Pago</Badge>
                  </span>
                </li>
              </ul>
              <div v-if="canManage" class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                <Button
                  v-if="Number(person.paid) > 0"
                  mobile-block
                  variant="ghost"
                  :loading="payingUser === person.user_id"
                  @click="unpayUser(person.user_id)"
                >
                  Desfazer pagamento
                </Button>
                <Button
                  v-if="Number(person.pending) > 0"
                  mobile-block
                  variant="secondary"
                  :loading="payingUser === person.user_id"
                  @click="payUser(person.user_id)"
                >
                  Marcar como pago
                </Button>
              </div>
            </div>
          </div>

          <div v-if="canManage" class="mt-4 flex sm:justify-end">
            <Button mobile-block variant="ghost" :loading="rebuilding" @click="rebuild()">
              Recalcular mês
            </Button>
          </div>
        </template>
      </SurfaceCard>

      <SurfaceCard>
        <SectionTitle
          title="Regras"
          hint="Vale a regra mais específica: pessoa + protocolo, depois pessoa, depois protocolo, depois a geral. Lançamentos já pagos não mudam."
        />
        <div v-if="rulesQuery.isPending.value" class="mt-4">
          <Skeleton class="h-12" />
        </div>
        <EmptyState
          v-else-if="(rulesQuery.data.value?.data ?? []).length === 0"
          title="Nenhuma regra"
          description="Sem regras, nenhuma comissão é lançada e a margem fica maior do que a real."
        >
        </EmptyState>
        <div v-else class="mt-2 divide-y divide-border-divider">
          <ListCard
            v-for="rule in rulesQuery.data.value?.data ?? []"
            :key="rule.id"
            :title="ruleTitle(rule)"
            :description="ruleDescription(rule)"
            :badge="rule.is_active ? '' : 'Inativa'"
            :show-chevron="canManage"
            @action="openEdit(rule)"
          />
        </div>
      </SurfaceCard>

      <GoalRemunerationsCard :can-manage="canManage" />

      <RevenueRemunerationsCard :can-manage="canManage" />
    </template>

    <AppDialog
      size="lg"
      v-model:open="dialogOpen"
      :title="editing ? 'Editar regra' : 'Nova regra de comissão'"
      description="Percentual sobre o valor ou valor fixo por venda/sessão."
    >
      <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
        <FormField label="Quem recebe" html-for="cr-role">
          <Select id="cr-role" v-model="form.role" :options="roleOptions" />
        </FormField>
        <FormField label="Pessoa" html-for="cr-user">
          <Select id="cr-user" v-model="form.user" :options="userOptions" />
        </FormField>
        <FormField label="Protocolo" html-for="cr-protocol">
          <Select id="cr-protocol" v-model="form.protocol" :options="protocolOptions" />
        </FormField>
        <FormField label="Tipo" html-for="cr-type">
          <Select id="cr-type" v-model="form.calc_type" :options="typeOptions" />
        </FormField>
        <FormField
          v-if="form.calc_type === 'percent'"
          label="Percentual"
          :error="errors.value"
          html-for="cr-value"
        >
          <template #default="{ invalid }">
            <Input
              id="cr-value"
              v-model="form.value"
              type="text"
              inputmode="decimal"
              placeholder="Ex.: 5"
              :invalid="invalid"
            />
          </template>
        </FormField>
        <FormField
          v-else
          :label="form.role === 'seller' ? 'Valor por venda' : 'Valor por sessão'"
          :error="errors.value"
          html-for="cr-value-fixed"
        >
          <template #default="{ invalid }">
            <MoneyInput id="cr-value-fixed" v-model="form.value" :invalid="invalid" />
          </template>
        </FormField>
        <FormField v-if="form.calc_type === 'percent'" label="Sobre" html-for="cr-base">
          <Select id="cr-base" v-model="form.base" :options="baseOptions" />
        </FormField>
      </form>
      <template #footer-negative>
        <Button
          v-if="editing"
          variant="ghost"
          type="button"
          :loading="toggling"
          @click="toggle(editing)"
        >
          {{ editing.is_active ? 'Desativar' : 'Reativar' }}
        </Button>
        <Button variant="ghost" type="button" @click="dialogOpen = false">Cancelar</Button>
      </template>
      <template #footer>
        <Button type="button" :loading="saving" @click="submit">Salvar</Button>
      </template>
    </AppDialog>
  </div>
</template>
