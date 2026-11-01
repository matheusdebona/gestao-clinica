<script setup lang="ts">
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import AppDialog from '@/components/ui/AppDialog.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import GoalMetrics from '@/features/goals/GoalMetrics.vue'
import {
  currentMonth,
  listTeamGoals,
  monthLabel,
  saveGoal,
  shiftMonth,
  type GoalProgress,
} from '@/features/goals/api'
import { emptyToMoney } from '@/lib/formatters'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'

const props = withDefaults(defineProps<{ allowEdit?: boolean }>(), { allowEdit: false })

const toast = useToastStore()
const queryClient = useQueryClient()

const thisMonth = currentMonth()
const month = ref(thisMonth)
const monthOptions = computed(() =>
  [-2, -1, 0, 1].map((delta) => {
    const value = shiftMonth(thisMonth, delta)
    return { value, label: monthLabel(value) }
  }),
)

const { data, isPending, isError } = useQuery({
  queryKey: ['goals', 'team', month],
  queryFn: () => listTeamGoals(month.value),
  placeholderData: keepPreviousData,
})

const rows = computed(() => data.value?.data ?? [])
const canEdit = computed(() => props.allowEdit && Boolean(data.value?.meta.can_edit))

const dialogOpen = ref(false)
const editing = ref<GoalProgress | null>(null)
const form = ref({ appointments: '', sales: '', approached: '' })
const formErrors = ref<Record<string, string>>({})

function openEdit(row: GoalProgress) {
  editing.value = row
  form.value = {
    appointments: row.targets.appointments_target?.toString() ?? '',
    sales: row.targets.sales_target ?? '',
    approached: row.targets.approached_target?.toString() ?? '',
  }
  formErrors.value = {}
  dialogOpen.value = true
}

function intOrNull(value: string): number | null {
  const trimmed = String(value ?? '').trim()
  return trimmed === '' ? null : Number(trimmed)
}

const { mutate, isPending: saving } = useMutation({
  mutationFn: () =>
    saveGoal(editing.value!.user.id, {
      month: month.value,
      appointments_target: intOrNull(form.value.appointments),
      sales_target: emptyToMoney(form.value.sales),
      approached_target: intOrNull(form.value.approached),
    }),
  onSuccess: async () => {
    toast.success('Meta salva')
    dialogOpen.value = false
    await queryClient.invalidateQueries({ queryKey: ['goals'] })
  },
  onError: (error) => {
    if (error instanceof ApiError && error.status === 422) {
      formErrors.value = {
        appointments: error.first('appointments_target'),
        sales: error.first('sales_target'),
        approached: error.first('approached_target'),
        month: error.first('month') || (Object.keys(error.errors).length ? '' : error.message),
      }
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível salvar.')
  },
})

function onSave() {
  for (const key of ['appointments', 'approached'] as const) {
    const value = intOrNull(form.value[key])
    if (value !== null && (!Number.isInteger(value) || value < 0)) {
      formErrors.value = { [key]: 'Informe um número inteiro a partir de zero.' }
      return
    }
  }
  mutate()
}

function inheritedHint(row: GoalProgress): string {
  return row.inherited_from ? `Meta de ${monthLabel(row.inherited_from)} (repetida)` : ''
}
</script>

<template>
  <SurfaceCard>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h2>Metas da equipe</h2>
        <p class="mt-1 text-[13px] text-muted">
          Agendamentos e leads abordados contam pelos marcos do lead; vendas pelo valor das vendas
          confirmadas no mês.
          <template v-if="data">
            Ritmo esperado: {{ data.data[0]?.expected_percent ?? 0 }}%.</template
          >
        </p>
      </div>
      <div class="w-full sm:w-48">
        <Select id="goals-month" v-model="month" :options="monthOptions" />
      </div>
    </div>

    <div v-if="isPending" class="mt-3"><Skeleton class="h-16" /></div>
    <p v-else-if="isError" class="mt-3 text-[15px] text-body">Não foi possível carregar.</p>
    <EmptyState
      v-else-if="rows.length === 0"
      title="Ninguém na equipe de leads e vendas"
      description="Cadastre colaboradores com acesso a leads ou vendas."
    />
    <ul v-else class="mt-3 flex flex-col divide-y divide-border-divider">
      <li v-for="row in rows" :key="row.user.id" class="flex flex-col gap-2 py-3 first:pt-0">
        <div class="flex items-baseline justify-between gap-3">
          <p class="text-[15px] font-medium text-title">{{ row.user.name }}</p>
          <p v-if="inheritedHint(row)" class="text-[13px] text-muted">{{ inheritedHint(row) }}</p>
        </div>
        <GoalMetrics v-if="row.has_goal" :progress="row" />
        <p v-else class="text-[13px] text-muted">Sem meta neste mês.</p>
        <div v-if="canEdit">
          <Button mobile-block variant="secondary" @click="openEdit(row)">
            {{ row.has_goal ? 'Editar meta' : 'Definir meta' }}
          </Button>
        </div>
      </li>
    </ul>

    <AppDialog
      v-model:open="dialogOpen"
      stack-footer
      title="Meta do mês"
      :description="editing ? `${editing.user.name} · ${monthLabel(month)}` : ''"
    >
      <div class="flex flex-col gap-4">
        <FormField
          label="Agendamentos"
          hint="Leads marcados como Agendou no mês. Vazio = sem meta."
          :error="formErrors.appointments"
          html-for="goal-appointments"
        >
          <template #default="{ invalid }">
            <Input
              id="goal-appointments"
              v-model="form.appointments"
              type="number"
              :invalid="invalid"
            />
          </template>
        </FormField>
        <FormField
          label="Valor de vendas"
          hint="Soma das vendas confirmadas no mês. Vazio = sem meta."
          :error="formErrors.sales"
          html-for="goal-sales"
        >
          <template #default="{ invalid }">
            <MoneyInput id="goal-sales" v-model="form.sales" :invalid="invalid" />
          </template>
        </FormField>
        <FormField
          label="Leads abordados (opcional)"
          :error="formErrors.approached"
          html-for="goal-approached"
        >
          <template #default="{ invalid }">
            <Input
              id="goal-approached"
              v-model="form.approached"
              type="number"
              :invalid="invalid"
            />
          </template>
        </FormField>
        <p v-if="formErrors.month" class="text-[13px] text-danger" role="alert">
          {{ formErrors.month }}
        </p>
        <p class="text-[13px] text-muted">
          Os meses seguintes repetem esta meta até você definir outra.
        </p>
      </div>
      <template #footer>
        <Button mobile-block variant="ghost" type="button" @click="dialogOpen = false">
          Cancelar
        </Button>
        <Button mobile-block type="button" :loading="saving" @click="onSave">Salvar meta</Button>
      </template>
    </AppDialog>
  </SurfaceCard>
</template>
