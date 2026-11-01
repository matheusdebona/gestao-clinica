<script setup lang="ts">
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { computed, reactive, ref } from 'vue'
import AppDialog from '@/components/ui/AppDialog.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import ListCard from '@/components/ui/ListCard.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Switch from '@/components/ui/Switch.vue'
import FinanceShortcuts from '@/features/finance/FinanceShortcuts.vue'
import { saveExpenseCategory } from '@/features/finance/api'
import {
  EXPENSE_KIND_LABELS,
  EXPENSE_KIND_OPTIONS,
  EXPENSE_KIND_ORDER,
} from '@/features/finance/labels'
import { useFinanceCatalog } from '@/features/finance/useFinanceCatalog'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { ExpenseCategory, ExpenseKind } from '@/types/finance'
import { ApiError } from '@/types/user'

const auth = useAuthStore()
const toast = useToastStore()
const queryClient = useQueryClient()
const canView = computed(() => auth.can('finance.view'))
const canManage = computed(() => auth.can('finance.manage'))
const catalog = useFinanceCatalog()

const KIND_HINTS: Record<ExpenseKind, string> = {
  fixed: 'Não variam com as vendas: aluguel, luz, água, internet, software, contador.',
  personnel: 'Salários, encargos e pró-labore dos sócios.',
  marketing: 'Mídia externa e produção; pode ser ligada a uma origem/campanha.',
  taxes:
    'Impostos fixos do mês (DAS do MEI, IPTU, alvarás). Os impostos sobre a receita ficam em Configurações → Impostos.',
  financial: 'Tarifas bancárias, juros e multas.',
  depreciation: 'Desgaste dos equipamentos (lançado sozinho).',
  variable: 'Outros custos que variam com o volume.',
}

const groups = computed(() =>
  EXPENSE_KIND_ORDER.map((kind) => ({
    kind,
    label: EXPENSE_KIND_LABELS[kind],
    hint: KIND_HINTS[kind],
    rows: catalog.categories.value.filter((c) => c.kind === kind),
  })),
)

const open = ref(false)
const editing = ref<ExpenseCategory | null>(null)
const form = reactive({ name: '', kind: 'fixed' as ExpenseKind, is_active: true })
const error = ref('')

function openNew(kind: ExpenseKind = 'fixed') {
  editing.value = null
  Object.assign(form, { name: '', kind, is_active: true })
  error.value = ''
  open.value = true
}

function openEdit(c: ExpenseCategory) {
  if (!canManage.value) return
  editing.value = c
  Object.assign(form, { name: c.name, kind: c.kind, is_active: c.is_active })
  error.value = ''
  open.value = true
}

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: () =>
    saveExpenseCategory(
      { name: form.name.trim(), kind: form.kind, is_active: form.is_active },
      editing.value?.id,
    ),
  onSuccess: async () => {
    open.value = false
    await queryClient.invalidateQueries({ queryKey: ['finance'] })
    toast.success('Categoria salva')
  },
  onError: (err) => {
    error.value =
      err instanceof ApiError ? err.first('name') || err.message : 'Não foi possível salvar.'
  },
})

function submit() {
  error.value = form.name.trim() ? '' : 'Informe o nome.'
  if (!error.value) save()
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader
      title="Categorias de despesa"
      description="A natureza de cada categoria define em qual linha do DRE a despesa aparece."
    >
      <template #actions>
        <Button v-if="canManage" mobile-block @click="openNew()">Nova categoria</Button>
      </template>
    </PageHeader>

    <Banner v-if="!canView" variant="danger" title="Sem permissão">
      Só a administração da clínica vê as despesas.
    </Banner>

    <template v-else>
      <FinanceShortcuts current="categories" />
      <SurfaceCard v-if="catalog.query.isPending.value">
        <Skeleton class="h-12" />
      </SurfaceCard>
      <template v-else>
        <SurfaceCard v-for="group in groups" :key="group.kind">
          <h2>{{ group.label }}</h2>
          <p class="mt-1 text-[13px] text-muted">{{ group.hint }}</p>
          <div class="mt-2 divide-y divide-border-divider">
            <ListCard
              v-for="c in group.rows"
              :key="c.id"
              :title="c.name"
              :badge="c.is_active ? '' : 'Inativa'"
              :show-chevron="canManage"
              @action="openEdit(c)"
            />
          </div>
        </SurfaceCard>
      </template>
    </template>

    <AppDialog
      v-model:open="open"
      :title="editing ? 'Editar categoria' : 'Nova categoria'"
      description="Categorias inativas somem dos formulários, mas os lançamentos antigos continuam."
    >
      <form class="flex flex-col gap-4" @submit.prevent="submit">
        <FormField label="Nome" :error="error" html-for="cat-name">
          <template #default="{ invalid }">
            <Input id="cat-name" v-model="form.name" type="text" :invalid="invalid" />
          </template>
        </FormField>
        <FormField label="Natureza" html-for="cat-kind">
          <Select id="cat-kind" v-model="form.kind" :options="EXPENSE_KIND_OPTIONS" />
        </FormField>
        <Switch v-if="editing" v-model="form.is_active" label="Ativa" />
      </form>
      <template #footer>
        <Button variant="ghost" type="button" @click="open = false">Cancelar</Button>
        <Button type="button" :loading="saving" @click="submit">Salvar</Button>
      </template>
    </AppDialog>
  </div>
</template>
