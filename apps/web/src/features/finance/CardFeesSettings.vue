<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, reactive, ref, watch } from 'vue'
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
import { ApiError } from '@/types/user'

const router = useRouter()
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
const anticipation = reactive<Record<number, { auto: boolean; rate: string; error: string }>>({})

watch(
  () => operatorsQuery.data.value,
  (operators) => {
    for (const op of operators ?? []) {
      anticipation[op.id] = {
        auto: op.auto_anticipate,
        rate: percentToInput(op.anticipation_rate_percent ?? null),
        error: '',
      }
    }
  },
  { immediate: true },
)

const savingOperator = ref<number | null>(null)
const { mutate: saveAnticipation } = useMutation({
  mutationFn: (id: number) =>
    updateOperatorAnticipation(id, {
      auto_anticipate: anticipation[id]!.auto,
      anticipation_rate_percent: toPercent(anticipation[id]!.rate),
    }),
  onMutate: (id) => {
    savingOperator.value = id
  },
  onSuccess: async () => {
    await queryClient.invalidateQueries({ queryKey: ['finance', 'card-operators'] })
    toast.success('Antecipação salva')
  },
  onError: (err, id) => {
    anticipation[id]!.error =
      err instanceof ApiError
        ? err.first('anticipation_rate_percent') || err.message
        : 'Não foi possível salvar.'
  },
  onSettled: () => {
    savingOperator.value = null
  },
})

function submitAnticipation(id: number) {
  const row = anticipation[id]!
  row.error = ''
  if (!isValidPercent(row.rate)) {
    row.error = 'Informe um percentual entre 0 e 100.'
    return
  }
  saveAnticipation(id)
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
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader
      title="Taxas de cartão"
      description="Taxa da maquininha por método, operadora, bandeira e parcelas, e taxa de antecipação. Entram na margem de contribuição de cada venda."
    >
      <template #actions>
        <Button mobile-block variant="ghost" @click="router.push({ name: 'settings' })">
          Configurações
        </Button>
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
        <h2>Regras de taxa</h2>
        <p class="mt-1 text-[13px] text-muted">
          Cadastre uma regra para cada combinação que a clínica usa (ex.: Crédito · Stone · Visa ·
          3x · 4,99%).
        </p>
        <EmptyState
          v-if="rules.length === 0"
          class="mt-4"
          title="Nenhuma regra cadastrada"
          description="Sem regras, os pagamentos de cartão ficam com taxa zero e a margem fica maior do que a real."
        >
          <template #action>
            <Button mobile-block @click="openNew">Nova regra</Button>
          </template>
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
        <h2>Antecipação</h2>
        <p class="mt-1 text-[13px] text-muted">
          Se a operadora antecipa os recebíveis do crédito, informe a taxa ao mês. Para n parcelas,
          o desconto é a taxa × (n + 1) ÷ 2 meses sobre o valor já sem a taxa da maquininha.
        </p>
        <p
          v-if="(operatorsQuery.data.value ?? []).length === 0"
          class="mt-4 text-[15px] text-muted"
        >
          Nenhuma operadora ativa.
        </p>
        <div class="mt-4 flex flex-col divide-y divide-border-divider">
          <div
            v-for="operator in operatorsQuery.data.value ?? []"
            :key="operator.id"
            class="flex flex-col gap-3 py-4 first:pt-0 last:pb-0"
          >
            <template v-if="anticipation[operator.id]">
              <div class="flex items-center justify-between gap-3">
                <h3>{{ operator.name }}</h3>
                <Badge v-if="anticipation[operator.id]!.auto" variant="purple">Antecipa</Badge>
              </div>
              <Switch v-model="anticipation[operator.id]!.auto" label="Antecipa automaticamente" />
              <FormField
                label="Taxa de antecipação (% ao mês)"
                :error="anticipation[operator.id]!.error"
                :html-for="`anticipation-${operator.id}`"
              >
                <template #default="{ invalid }">
                  <Input
                    :id="`anticipation-${operator.id}`"
                    v-model="anticipation[operator.id]!.rate"
                    type="text"
                    inputmode="decimal"
                    placeholder="Ex.: 1,99"
                    :disabled="!anticipation[operator.id]!.auto"
                    :invalid="invalid"
                  />
                </template>
              </FormField>
              <div class="flex sm:justify-end">
                <Button
                  mobile-block
                  variant="secondary"
                  :loading="savingOperator === operator.id"
                  @click="submitAnticipation(operator.id)"
                >
                  Salvar antecipação
                </Button>
              </div>
            </template>
          </div>
        </div>
      </SurfaceCard>

      <SurfaceCard>
        <h2>Recalcular pagamentos</h2>
        <p class="mt-1 text-[13px] text-muted">
          Novos pagamentos já usam as regras atuais. Use isto depois de cadastrar ou corrigir taxas
          para aplicá-las também às vendas antigas.
        </p>
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
      v-model:open="dialogOpen"
      :title="editing ? 'Editar regra' : 'Nova regra'"
      description="A taxa vale para pagamentos com exatamente esta combinação."
    >
      <form class="flex flex-col gap-4" @submit.prevent="submitRule">
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
          label="Taxa fixa por transação"
          hint="Opcional."
          :error="formErrors.fee_fixed"
          html-for="rule-fixed"
        >
          <template #default="{ invalid }">
            <MoneyInput id="rule-fixed" v-model="form.fixed" :invalid="invalid" />
          </template>
        </FormField>
      </form>
      <template #footer>
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
  </div>
</template>
