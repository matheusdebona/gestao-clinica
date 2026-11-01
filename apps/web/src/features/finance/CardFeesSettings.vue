<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, reactive, ref, watch } from 'vue'
import SectionTitle from '@/components/ui/SectionTitle.vue'
import AppDialog from '@/components/ui/AppDialog.vue'
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
import Switch from '@/components/ui/Switch.vue'
import {
  createCardFeeRule,
  isValidPercent,
  listCardFeeRules,
  percentToInput,
  recalculatePaymentFees,
  toPercent,
  updateCardFeeRule,
  updateOperatorAnticipation,
} from '@/features/finance/api'
import { formatFeePercent } from '@/features/payments/labels'
import { listCardBrands, listCardOperators, listPaymentMethods } from '@/features/payments/api'
import { emptyToMoney, formatBRL } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { CardFeeRule } from '@/types/finance'
import type { CardOperator } from '@/types/sale'
import { ApiError } from '@/types/user'

const auth = useAuthStore()
const toast = useToastStore()
const queryClient = useQueryClient()

const canManage = computed(() => auth.can('finance.manage') && auth.can('card_fees.manage'))

const rulesQuery = useQuery({
  queryKey: ['finance', 'card-fee-rules'],
  queryFn: listCardFeeRules,
  enabled: canManage,
})
const methodsQuery = useQuery({
  queryKey: ['payment-methods', 'active'],
  queryFn: listPaymentMethods,
  enabled: canManage,
})
const operatorsQuery = useQuery({
  queryKey: ['finance', 'card-operators'],
  queryFn: listCardOperators,
  enabled: canManage,
})
const brandsQuery = useQuery({
  queryKey: ['card-brands', 'active'],
  queryFn: listCardBrands,
  enabled: canManage,
})

const cardMethods = computed(() =>
  (methodsQuery.data.value ?? []).filter(
    (m) => m.kind === 'credit_card' || m.kind === 'debit_card',
  ),
)
const methodOptions = computed(() =>
  cardMethods.value.map((m) => ({ value: String(m.id), label: m.name })),
)
const operatorOptions = computed(() =>
  (operatorsQuery.data.value ?? []).map((o) => ({ value: String(o.id), label: o.name })),
)
const brandOptions = computed(() =>
  (brandsQuery.data.value ?? []).map((b) => ({ value: String(b.id), label: b.name })),
)

const rules = computed(() =>
  [...(rulesQuery.data.value ?? [])].sort((a, b) => {
    const key = (r: CardFeeRule) =>
      `${r.is_active ? 0 : 1}|${r.payment_method?.name ?? ''}|${r.card_operator?.name ?? ''}|${r.card_brand?.name ?? ''}|${String(r.installments).padStart(2, '0')}`
    return key(a).localeCompare(key(b))
  }),
)

const pending = computed(
  () =>
    rulesQuery.isPending.value ||
    methodsQuery.isPending.value ||
    operatorsQuery.isPending.value ||
    brandsQuery.isPending.value,
)
const loadError = computed(
  () =>
    rulesQuery.isError.value ||
    methodsQuery.isError.value ||
    operatorsQuery.isError.value ||
    brandsQuery.isError.value,
)

function ruleTitle(rule: CardFeeRule): string {
  return [rule.payment_method?.name, rule.card_operator?.name, rule.card_brand?.name]
    .filter(Boolean)
    .join(' · ')
}

function ruleDescription(rule: CardFeeRule): string {
  const parts: string[] = [`${rule.installments}x`]
  if (rule.fee_percent !== null) parts.push(formatFeePercent(rule.fee_percent))
  if (rule.fee_fixed !== null && Number(rule.fee_fixed) > 0)
    parts.push(`+ ${formatBRL(rule.fee_fixed)}`)
  return parts.join(' · ')
}

/* ---------- Antecipação por operadora ---------- */
// Lives on card_operators (auto_anticipate + anticipation_rate_percent). The list only shows the
// operators the clinic configured; "adicionar" picks one of the others, "remover" clears both.
const ANTICIPATION_HINT =
  'Se a operadora antecipa os recebíveis do crédito, informe a taxa ao mês. Para n parcelas, o desconto é a taxa × (n + 1) ÷ 2 meses sobre o valor já sem a taxa da maquininha.'

const configuredOperators = computed(() =>
  (operatorsQuery.data.value ?? []).filter(
    (op) => op.auto_anticipate || Number(op.anticipation_rate_percent ?? 0) > 0,
  ),
)

function anticipationDescription(op: CardOperator): string {
  const rate =
    Number(op.anticipation_rate_percent ?? 0) > 0
      ? `${formatFeePercent(op.anticipation_rate_percent!)} ao mês`
      : 'Sem taxa informada'
  return `${rate} · ${op.auto_anticipate ? 'automática' : 'desligada'}`
}

const anticipationOpen = ref(false)
const anticipationEditing = ref<CardOperator | null>(null)
const anticipationForm = reactive({ operator: '', rate: '', auto: true })
const anticipationErrors = reactive({ operator: '', rate: '' })
const confirmRemoveAnticipation = ref(false)

const anticipationOperatorOptions = computed(() => {
  const taken = new Set(configuredOperators.value.map((op) => op.id))
  return (operatorsQuery.data.value ?? [])
    .filter((op) => !taken.has(op.id) || op.id === anticipationEditing.value?.id)
    .map((op) => ({ value: String(op.id), label: op.name }))
})

function openNewAnticipation() {
  anticipationEditing.value = null
  Object.assign(anticipationForm, { operator: '', rate: '', auto: true })
  Object.assign(anticipationErrors, { operator: '', rate: '' })
  anticipationOpen.value = true
}

function openEditAnticipation(op: CardOperator) {
  anticipationEditing.value = op
  Object.assign(anticipationForm, {
    operator: String(op.id),
    rate: percentToInput(op.anticipation_rate_percent ?? null),
    auto: op.auto_anticipate,
  })
  Object.assign(anticipationErrors, { operator: '', rate: '' })
  anticipationOpen.value = true
}

const { mutate: saveAnticipation, isPending: savingAnticipation } = useMutation({
  mutationFn: (payload: { id: number; auto: boolean; rate: string | null }) =>
    updateOperatorAnticipation(payload.id, {
      auto_anticipate: payload.auto,
      anticipation_rate_percent: payload.rate,
    }),
  onSuccess: async (_data, payload) => {
    anticipationOpen.value = false
    await queryClient.invalidateQueries({ queryKey: ['finance', 'card-operators'] })
    toast.success(payload.rate === null && !payload.auto ? 'Antecipação removida' : 'Antecipação salva')
  },
  onError: (err) => {
    anticipationErrors.rate =
      err instanceof ApiError
        ? err.first('anticipation_rate_percent') || err.message
        : 'Não foi possível salvar.'
  },
})

function submitAnticipation() {
  Object.assign(anticipationErrors, { operator: '', rate: '' })
  if (!anticipationForm.operator) anticipationErrors.operator = 'Escolha a operadora.'
  const rate = toPercent(anticipationForm.rate)
  if (!isValidPercent(anticipationForm.rate) || rate === null || Number(rate) <= 0)
    anticipationErrors.rate = 'Informe a taxa ao mês, entre 0 e 100.'
  if (anticipationErrors.operator || anticipationErrors.rate) return
  const id = Number(anticipationForm.operator)
  // Switching operator while editing: clear the old one first.
  const previous = anticipationEditing.value
  if (previous && previous.id !== id) {
    updateOperatorAnticipation(previous.id, { auto_anticipate: false, anticipation_rate_percent: null })
  }
  saveAnticipation({ id, auto: anticipationForm.auto, rate })
}

function removeAnticipation() {
  const op = anticipationEditing.value
  if (!op) return
  saveAnticipation({ id: op.id, auto: false, rate: null })
}

/* ---------- Regra de taxa ---------- */
const dialogOpen = ref(false)
const editing = ref<CardFeeRule | null>(null)
const form = reactive({
  method: '',
  operator: '',
  brand: '',
  installments: '1',
  percent: '',
  fixed: '',
})
const formErrors = reactive<Record<string, string>>({})

const selectedMethodIsDebit = computed(
  () => cardMethods.value.find((m) => String(m.id) === form.method)?.kind === 'debit_card',
)

watch(selectedMethodIsDebit, (debit) => {
  if (debit) form.installments = '1'
})

function openNew() {
  editing.value = null
  Object.assign(form, {
    method: methodOptions.value[0]?.value ?? '',
    operator: operatorOptions.value[0]?.value ?? '',
    brand: brandOptions.value[0]?.value ?? '',
    installments: '1',
    percent: '',
    fixed: '',
  })
  Object.keys(formErrors).forEach((k) => delete formErrors[k])
  dialogOpen.value = true
}

function openEdit(rule: CardFeeRule) {
  editing.value = rule
  Object.assign(form, {
    method: String(rule.payment_method_id),
    operator: String(rule.card_operator_id),
    brand: String(rule.card_brand_id),
    installments: String(rule.installments),
    percent: percentToInput(rule.fee_percent),
    fixed: rule.fee_fixed ?? '',
  })
  Object.keys(formErrors).forEach((k) => delete formErrors[k])
  dialogOpen.value = true
}

const { mutate: saveRule, isPending: savingRule } = useMutation({
  mutationFn: () => {
    const body = {
      payment_method_id: Number(form.method),
      card_operator_id: Number(form.operator),
      card_brand_id: Number(form.brand),
      installments: Number(form.installments),
      fee_percent: toPercent(form.percent),
      fee_fixed: emptyToMoney(String(form.fixed ?? '')),
      is_active: true,
    }
    return editing.value ? updateCardFeeRule(editing.value.id, body) : createCardFeeRule(body)
  },
  onSuccess: async () => {
    dialogOpen.value = false
    await queryClient.invalidateQueries({ queryKey: ['finance', 'card-fee-rules'] })
    toast.success('Regra salva')
  },
  onError: (err) => {
    if (err instanceof ApiError) {
      for (const field of [
        'payment_method_id',
        'card_operator_id',
        'card_brand_id',
        'installments',
        'fee_percent',
        'fee_fixed',
      ]) {
        const message = err.first(field)
        if (message) formErrors[field] = message
      }
      if (Object.keys(formErrors).length === 0) formErrors.fee_percent = err.message
    } else {
      formErrors.fee_percent = 'Não foi possível salvar.'
    }
  },
})

function submitRule() {
  Object.keys(formErrors).forEach((k) => delete formErrors[k])
  if (!form.method) formErrors.payment_method_id = 'Selecione o método.'
  if (!form.operator) formErrors.card_operator_id = 'Selecione a operadora.'
  if (!form.brand) formErrors.card_brand_id = 'Selecione a bandeira.'
  const installments = Number(form.installments)
  if (!Number.isInteger(installments) || installments < 1 || installments > 48) {
    formErrors.installments = 'Parcelas de 1 a 48.'
  }
  if (!isValidPercent(form.percent)) formErrors.fee_percent = 'Percentual entre 0 e 100.'
  if (toPercent(form.percent) === null && !emptyToMoney(String(form.fixed ?? ''))) {
    formErrors.fee_percent = 'Informe a taxa percentual e/ou a taxa fixa.'
  }
  if (Object.keys(formErrors).length === 0) saveRule()
}

const { mutate: toggleRule, isPending: togglingRule } = useMutation({
  mutationFn: (rule: CardFeeRule) => updateCardFeeRule(rule.id, { is_active: !rule.is_active }),
  onSuccess: async () => {
    dialogOpen.value = false
    await queryClient.invalidateQueries({ queryKey: ['finance', 'card-fee-rules'] })
  },
})

/* ---------- Recalcular ---------- */
const recalcOpen = ref(false)
const { mutate: recalc, isPending: recalculating } = useMutation({
  mutationFn: () => recalculatePaymentFees(),
  onSuccess: async (count) => {
    await queryClient.invalidateQueries({ queryKey: ['metrics'] })
    toast.success(`${count} pagamentos recalculados`)
  },
  onError: () => toast.error('Não foi possível recalcular.'),
})
</script>

<template>
  <div class="mx-auto flex w-full max-w-[960px] flex-col gap-6">
    <PageHeader
      :back="{ label: 'Configurações', to: { name: 'settings' } }"
      title="Taxas de cartão"
      description="Taxa da maquininha por método, operadora, bandeira e parcelas, e taxa de antecipação. Entram na margem de contribuição de cada venda."
    >
      <template #actions>
        <Button v-if="canManage" mobile-block @click="openNew">Nova regra</Button>
      </template>
    </PageHeader>

    <Banner v-if="!canManage" variant="danger" title="Sem permissão">
      Só a administração da clínica altera taxas de cartão.
    </Banner>

    <Banner v-else-if="loadError" variant="danger" title="Não foi possível carregar">
      Tente de novo em instantes.
    </Banner>

    <SurfaceCard v-else-if="pending">
      <Skeleton class="h-12" />
      <Skeleton class="mt-3 h-12" />
      <Skeleton class="mt-3 h-12" />
    </SurfaceCard>

    <template v-else>
      <Banner variant="info" title="Como a taxa é escolhida">
        Cada pagamento usa a regra com o mesmo método, operadora, bandeira e parcelas. Sem regra,
        usa a taxa do método (Pix, boleto…). Sem nenhuma, a taxa fica zero.
      </Banner>

      <SurfaceCard>
        <SectionTitle
          title="Regras de taxa"
          hint="Cadastre uma regra para cada combinação que a clínica usa (ex.: Crédito · Stone · Visa · 3x · 4,99%)."
        />
        <EmptyState
          v-if="rules.length === 0"
          class="mt-4"
          title="Nenhuma regra cadastrada"
          description="Sem regras, os pagamentos de cartão ficam com taxa zero e a margem fica maior do que a real."
        >
        </EmptyState>
        <div v-else class="mt-2 divide-y divide-border-divider">
          <ListCard
            v-for="rule in rules"
            :key="rule.id"
            :title="ruleTitle(rule)"
            :description="ruleDescription(rule)"
            :badge="rule.is_active ? '' : 'Inativa'"
            badge-variant="muted"
            @action="openEdit(rule)"
          />
        </div>
      </SurfaceCard>

      <SurfaceCard>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <SectionTitle
            title="Antecipação"
            :hint="ANTICIPATION_HINT"
            description="Só as operadoras que antecipam o crédito."
          />
          <Button mobile-block variant="secondary" @click="openNewAnticipation">
            + Adicionar antecipação
          </Button>
        </div>
        <EmptyState
          v-if="configuredOperators.length === 0"
          title="Nenhuma antecipação"
          description="Adicione quando usar uma operadora que antecipa os recebíveis."
        />
        <div v-else class="mt-2 divide-y divide-border-divider">
          <ListCard
            v-for="op in configuredOperators"
            :key="op.id"
            :title="op.name"
            :description="anticipationDescription(op)"
            :badge="op.auto_anticipate ? 'Antecipa' : ''"
            badge-variant="purple"
            show-chevron
            @action="openEditAnticipation(op)"
          />
        </div>
      </SurfaceCard>

      <SurfaceCard>
        <SectionTitle
          title="Recalcular pagamentos"
          hint="Novos pagamentos já usam as regras atuais. Use isto depois de cadastrar ou corrigir taxas para aplicá-las também às vendas antigas."
        />
        <div class="mt-4 flex sm:justify-end">
          <Button
            mobile-block
            variant="secondary"
            :loading="recalculating"
            @click="recalcOpen = true"
          >
            Recalcular todas as vendas
          </Button>
        </div>
      </SurfaceCard>
    </template>

    <AppDialog
      size="lg"
      v-model:open="dialogOpen"
      :title="editing ? 'Editar regra' : 'Nova regra'"
      description="A taxa vale para pagamentos com exatamente esta combinação."
    >
      <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submitRule">
        <FormField label="Método" :error="formErrors.payment_method_id" html-for="rule-method">
          <template #default="{ invalid }">
            <Select
              id="rule-method"
              v-model="form.method"
              :options="methodOptions"
              :invalid="invalid"
            />
          </template>
        </FormField>
        <FormField label="Operadora" :error="formErrors.card_operator_id" html-for="rule-operator">
          <template #default="{ invalid }">
            <Select
              id="rule-operator"
              v-model="form.operator"
              :options="operatorOptions"
              :invalid="invalid"
            />
          </template>
        </FormField>
        <FormField label="Bandeira" :error="formErrors.card_brand_id" html-for="rule-brand">
          <template #default="{ invalid }">
            <Select
              id="rule-brand"
              v-model="form.brand"
              :options="brandOptions"
              :invalid="invalid"
            />
          </template>
        </FormField>
        <FormField
          label="Parcelas"
          :hint="selectedMethodIsDebit ? 'Débito é sempre 1x.' : ''"
          :error="formErrors.installments"
          html-for="rule-installments"
        >
          <template #default="{ invalid }">
            <Input
              id="rule-installments"
              v-model="form.installments"
              type="number"
              inputmode="numeric"
              :disabled="selectedMethodIsDebit"
              :invalid="invalid"
            />
          </template>
        </FormField>
        <FormField label="Taxa percentual" :error="formErrors.fee_percent" html-for="rule-percent">
          <template #default="{ invalid }">
            <Input
              id="rule-percent"
              v-model="form.percent"
              type="text"
              inputmode="decimal"
              placeholder="Ex.: 4,99"
              :invalid="invalid"
            />
          </template>
        </FormField>
        <FormField
          label="Taxa fixa por transação (opcional)"
          :error="formErrors.fee_fixed"
          html-for="rule-fixed"
        >
          <template #default="{ invalid }">
            <MoneyInput id="rule-fixed" v-model="form.fixed" :invalid="invalid" />
          </template>
        </FormField>
      </form>
      <template #footer-negative>
        <Button
          v-if="editing"
          variant="ghost"
          type="button"
          :loading="togglingRule"
          @click="toggleRule(editing)"
        >
          {{ editing.is_active ? 'Desativar' : 'Reativar' }}
        </Button>
        <Button variant="ghost" type="button" @click="dialogOpen = false">Cancelar</Button>
      </template>
      <template #footer>
        <Button type="button" :loading="savingRule" @click="submitRule">Salvar</Button>
      </template>
    </AppDialog>

    <ConfirmDialog
      v-model:open="recalcOpen"
      title="Recalcular taxas?"
      description="Todas as vendas passam a usar as regras e taxas de antecipação atuais. Isto muda a margem de meses anteriores."
      confirm-label="Recalcular"
      confirm-variant="primary"
      @confirm="recalc()"
    />

    <AppDialog
      size="md"
      v-model:open="anticipationOpen"
      :title="anticipationEditing ? 'Editar antecipação' : 'Adicionar antecipação'"
      description="Vale para pagamentos no crédito com esta operadora."
    >
      <form class="flex flex-col gap-4" @submit.prevent="submitAnticipation">
        <FormField label="Operadora" :error="anticipationErrors.operator" html-for="ant-operator">
          <Select
            id="ant-operator"
            v-model="anticipationForm.operator"
            :options="anticipationOperatorOptions"
            placeholder="Escolha a operadora"
            :invalid="Boolean(anticipationErrors.operator)"
          />
        </FormField>
        <FormField
          label="Taxa ao mês (%)"
          :hint="ANTICIPATION_HINT"
          :error="anticipationErrors.rate"
          html-for="ant-rate"
        >
          <template #default="{ invalid }">
            <Input
              id="ant-rate"
              v-model="anticipationForm.rate"
              type="text"
              inputmode="decimal"
              placeholder="Ex.: 1,99"
              :invalid="invalid"
            />
          </template>
        </FormField>
        <Switch v-model="anticipationForm.auto" label="Antecipa automaticamente" />
      </form>
      <template #footer-negative>
        <Button
          v-if="anticipationEditing"
          variant="ghost"
          type="button"
          @click="confirmRemoveAnticipation = true"
        >
          Remover
        </Button>
        <Button variant="ghost" type="button" @click="anticipationOpen = false">Cancelar</Button>
      </template>
      <template #footer>
        <Button type="button" :loading="savingAnticipation" @click="submitAnticipation">
          Salvar
        </Button>
      </template>
    </AppDialog>

    <ConfirmDialog
      v-model:open="confirmRemoveAnticipation"
      title="Remover antecipação?"
      :description="`Novos pagamentos com ${anticipationEditing?.name ?? 'a operadora'} deixam de descontar antecipação. Use Recalcular para aplicar às vendas antigas.`"
      confirm-label="Remover"
      @confirm="removeAnticipation"
    />
  </div>
</template>
