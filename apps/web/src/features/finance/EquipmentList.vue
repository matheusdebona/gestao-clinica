<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, reactive, ref } from 'vue'
import MonthSelect from '@/components/patterns/MonthSelect.vue'
import AppDialog from '@/components/ui/AppDialog.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import DatePicker from '@/components/ui/DatePicker.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import ListCard from '@/components/ui/ListCard.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Switch from '@/components/ui/Switch.vue'
import Textarea from '@/components/ui/Textarea.vue'
import FinanceShortcuts from '@/features/finance/FinanceShortcuts.vue'
import { currentMonthKey, listEquipment, saveEquipment } from '@/features/finance/api'
import { formatMonthLabel } from '@/features/finance/labels'
import { emptyToMoney, formatBRL } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { EquipmentAsset } from '@/types/finance'
import { ApiError } from '@/types/user'

const auth = useAuthStore()
const toast = useToastStore()
const queryClient = useQueryClient()
const canView = computed(() => auth.can('finance.view'))
const canManage = computed(() => auth.can('finance.manage'))

const { data, isPending, isError } = useQuery({
  queryKey: ['finance', 'equipment'],
  queryFn: listEquipment,
  enabled: canView,
})

const monthlyTotal = computed(() =>
  (data.value ?? [])
    .filter((a) => a.is_active && a.depreciation_end_month >= currentMonthKey())
    .reduce((sum, a) => sum + Number(a.monthly_depreciation), 0),
)

function describe(a: EquipmentAsset): string {
  return `${formatBRL(a.monthly_depreciation)}/mês · ${formatMonthLabel(a.depreciation_start_month)} a ${formatMonthLabel(a.depreciation_end_month)} · valor atual ${formatBRL(a.book_value)}`
}

const open = ref(false)
const editing = ref<EquipmentAsset | null>(null)
const form = reactive({
  name: '',
  purchase_value: '',
  residual_value: '',
  purchased_on: '',
  useful_life_months: '60',
  start: currentMonthKey(),
  is_active: true,
  notes: '',
})
const errors = reactive<Record<string, string>>({})

const preview = computed(() => {
  const purchase = Number(emptyToMoney(String(form.purchase_value)) ?? 0)
  const residual = Number(emptyToMoney(String(form.residual_value)) ?? 0)
  const life = Number(form.useful_life_months)
  if (!(purchase > 0) || !(life > 0) || residual >= purchase) return null
  return (purchase - residual) / life
})

function clear() {
  Object.keys(errors).forEach((k) => delete errors[k])
}

function openNew() {
  editing.value = null
  Object.assign(form, {
    name: '',
    purchase_value: '',
    residual_value: '',
    purchased_on: '',
    useful_life_months: '60',
    start: currentMonthKey(),
    is_active: true,
    notes: '',
  })
  clear()
  open.value = true
}

function openEdit(a: EquipmentAsset) {
  if (!canManage.value) return
  editing.value = a
  Object.assign(form, {
    name: a.name,
    purchase_value: Number(a.purchase_value).toFixed(2),
    residual_value: Number(a.residual_value) > 0 ? Number(a.residual_value).toFixed(2) : '',
    purchased_on: a.purchased_on ?? '',
    useful_life_months: String(a.useful_life_months),
    start: a.depreciation_start_month,
    is_active: a.is_active,
    notes: a.notes ?? '',
  })
  clear()
  open.value = true
}

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: () =>
    saveEquipment(
      {
        name: form.name.trim(),
        purchase_value: emptyToMoney(String(form.purchase_value)) ?? '0',
        residual_value: emptyToMoney(String(form.residual_value)),
        purchased_on: form.purchased_on || null,
        useful_life_months: Number(form.useful_life_months),
        depreciation_start_month: form.start,
        is_active: form.is_active,
        notes: form.notes.trim() || null,
      },
      editing.value?.id,
    ),
  onSuccess: async () => {
    open.value = false
    await queryClient.invalidateQueries({ queryKey: ['finance'] })
    toast.success('Equipamento salvo')
  },
  onError: (err) => {
    if (err instanceof ApiError) {
      for (const f of ['name', 'purchase_value', 'residual_value', 'useful_life_months']) {
        const m = err.first(f)
        if (m) errors[f] = m
      }
      if (!Object.keys(errors).length) errors.name = err.message
    } else errors.name = 'Não foi possível salvar.'
  },
})

function submit() {
  clear()
  if (!form.name.trim()) errors.name = 'Informe o nome.'
  const purchase = Number(emptyToMoney(String(form.purchase_value)) ?? 0)
  if (!(purchase > 0)) errors.purchase_value = 'Informe o valor de compra.'
  const residual = Number(emptyToMoney(String(form.residual_value)) ?? 0)
  if (residual >= purchase && purchase > 0)
    errors.residual_value = 'O valor residual deve ser menor que o de compra.'
  const life = Number(form.useful_life_months)
  if (!Number.isInteger(life) || life < 1 || life > 600)
    errors.useful_life_months = 'Vida útil de 1 a 600 meses.'
  if (!Object.keys(errors).length) save()
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader
      title="Equipamentos"
      description="Laser, autoclave, macas… depreciados todo mês pela vida útil. A depreciação entra nas despesas e no DRE."
    >
      <template #actions>
        <Button v-if="canManage" mobile-block @click="openNew">Novo equipamento</Button>
      </template>
    </PageHeader>

    <Banner v-if="!canView" variant="danger" title="Sem permissão">
      Só a administração da clínica vê os equipamentos.
    </Banner>

    <template v-else>
      <FinanceShortcuts current="equipment" />
      <Banner v-if="isError" variant="danger" title="Não foi possível carregar">
        Tente de novo em instantes.
      </Banner>
      <SurfaceCard v-else-if="isPending">
        <Skeleton class="h-12" />
      </SurfaceCard>
      <SurfaceCard v-else>
        <div class="flex items-baseline justify-between gap-3">
          <h2>Depreciação</h2>
          <span class="shrink-0 tabular-nums text-[15px] font-semibold text-title">
            {{ formatBRL(monthlyTotal) }}/mês
          </span>
        </div>
        <EmptyState
          v-if="(data ?? []).length === 0"
          title="Nenhum equipamento"
          description="Cadastre os equipamentos com valor de compra e vida útil para o DRE considerar o desgaste."
        >
          <template v-if="canManage" #action>
            <Button mobile-block @click="openNew">Novo equipamento</Button>
          </template>
        </EmptyState>
        <div v-else class="mt-2 divide-y divide-border-divider">
          <ListCard
            v-for="a in data"
            :key="a.id"
            :title="`${a.name} · ${formatBRL(a.purchase_value)}`"
            :description="describe(a)"
            :badge="a.is_active ? '' : 'Inativo'"
            :show-chevron="canManage"
            @action="openEdit(a)"
          />
        </div>
      </SurfaceCard>
    </template>

    <AppDialog
      v-model:open="open"
      :title="editing ? 'Editar equipamento' : 'Novo equipamento'"
      description="Depreciação linear: (compra − residual) ÷ vida útil."
    >
      <form class="flex flex-col gap-4" @submit.prevent="submit">
        <FormField label="Nome" :error="errors.name" html-for="eq-name">
          <template #default="{ invalid }">
            <Input id="eq-name" v-model="form.name" type="text" :invalid="invalid" />
          </template>
        </FormField>
        <FormField label="Valor de compra" :error="errors.purchase_value" html-for="eq-purchase">
          <template #default="{ invalid }">
            <MoneyInput id="eq-purchase" v-model="form.purchase_value" :invalid="invalid" />
          </template>
        </FormField>
        <FormField
          label="Valor residual"
          hint="Quanto vale no fim da vida útil. Opcional."
          :error="errors.residual_value"
          html-for="eq-residual"
        >
          <template #default="{ invalid }">
            <MoneyInput id="eq-residual" v-model="form.residual_value" :invalid="invalid" />
          </template>
        </FormField>
        <FormField
          label="Vida útil (meses)"
          hint="Ex.: 60 meses (5 anos)."
          :error="errors.useful_life_months"
          html-for="eq-life"
        >
          <template #default="{ invalid }">
            <Input
              id="eq-life"
              v-model="form.useful_life_months"
              type="number"
              inputmode="numeric"
              :invalid="invalid"
            />
          </template>
        </FormField>
        <FormField label="Começa a depreciar em" html-for="eq-start">
          <MonthSelect id="eq-start" v-model="form.start" :past="120" />
        </FormField>
        <FormField label="Data da compra" hint="Opcional." html-for="eq-purchased">
          <DatePicker id="eq-purchased" v-model="form.purchased_on" />
        </FormField>
        <FormField label="Observações" html-for="eq-notes">
          <Textarea id="eq-notes" v-model="form.notes" />
        </FormField>
        <Switch v-if="editing" v-model="form.is_active" label="Ativo" />
        <p v-if="preview !== null" class="text-[15px] text-title">
          Depreciação: <strong>{{ formatBRL(preview) }}/mês</strong>
        </p>
      </form>
      <template #footer>
        <Button variant="ghost" type="button" @click="open = false">Cancelar</Button>
        <Button type="button" :loading="saving" @click="submit">Salvar</Button>
      </template>
    </AppDialog>
  </div>
</template>
