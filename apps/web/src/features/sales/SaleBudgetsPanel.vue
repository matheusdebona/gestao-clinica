<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import ListCard from '@/components/ui/ListCard.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Textarea from '@/components/ui/Textarea.vue'
import {
  acceptBudget,
  createBudget,
  expireBudget,
  generateBudgetPdf,
  downloadDocument,
  listSaleBudgets,
  rejectBudget,
  sendBudget,
} from '@/features/budgets/api'
import { BUDGET_STATUS_BADGE, BUDGET_STATUS_LABELS } from '@/features/sales/labels'
import { formatBRL, formatDateTime } from '@/lib/formatters'
import { useToastStore } from '@/stores/toast'
import type { Budget } from '@/types/budget'
import { ApiError } from '@/types/user'

const props = defineProps<{
  saleId: number
  canCreate: boolean
}>()

const toast = useToastStore()
const queryClient = useQueryClient()
const notes = ref('')
const validUntil = ref('')
const pendingAction = ref<{ id: number; kind: 'reject' | 'expire' } | null>(null)
const confirmOpen = computed({
  get: () => pendingAction.value !== null,
  set: (open: boolean) => {
    if (!open) {
      pendingAction.value = null
    }
  },
})

const saleIdRef = computed(() => props.saleId)

const { data, isPending, isError } = useQuery({
  queryKey: ['budgets', 'sale', saleIdRef],
  queryFn: () => listSaleBudgets(saleIdRef.value),
  enabled: computed(() => saleIdRef.value > 0),
})

const budgets = computed(() => data.value?.data ?? [])

function invalidate() {
  return queryClient.invalidateQueries({ queryKey: ['budgets'] })
}

function onError(error: unknown, fallback: string) {
  toast.error(error instanceof ApiError ? error.message : fallback)
}

const createMutation = useMutation({
  mutationFn: () =>
    createBudget(props.saleId, {
      notes: notes.value.trim() || null,
      valid_until: validUntil.value || null,
    }),
  onSuccess: async () => {
    toast.success('Orçamento gerado')
    notes.value = ''
    validUntil.value = ''
    await invalidate()
  },
  onError: (error) => onError(error, 'Não foi possível gerar o orçamento.'),
})

const sendMutation = useMutation({
  mutationFn: (id: number) => sendBudget(id),
  onSuccess: async () => {
    toast.success('Orçamento enviado')
    await invalidate()
  },
  onError: (error) => onError(error, 'Não foi possível enviar.'),
})

const acceptMutation = useMutation({
  mutationFn: (id: number) => acceptBudget(id),
  onSuccess: async () => {
    toast.success('Orçamento aceito')
    await invalidate()
  },
  onError: (error) => onError(error, 'Não foi possível aceitar.'),
})

const rejectMutation = useMutation({
  mutationFn: (id: number) => rejectBudget(id),
  onSuccess: async () => {
    toast.success('Orçamento recusado')
    await invalidate()
  },
  onError: (error) => onError(error, 'Não foi possível recusar.'),
})

const expireMutation = useMutation({
  mutationFn: (id: number) => expireBudget(id),
  onSuccess: async () => {
    toast.success('Orçamento expirado')
    await invalidate()
  },
  onError: (error) => onError(error, 'Não foi possível expirar.'),
})

const pdfMutation = useMutation({
  mutationFn: async (budget: Budget) => {
    const document = await generateBudgetPdf(budget.id)
    await downloadDocument(document.id, document.filename)
  },
  onSuccess: () => {
    toast.success('PDF gerado')
  },
  onError: (error) => onError(error, 'Não foi possível gerar o PDF.'),
})

function budgetMeta(budget: Budget) {
  return `v${budget.version} · ${formatBRL(budget.effective_amount)} · ${formatDateTime(budget.created_at)}`
}

function onConfirmPending() {
  const action = pendingAction.value
  if (!action) {
    return
  }
  if (action.kind === 'reject') {
    rejectMutation.mutate(action.id)
  } else {
    expireMutation.mutate(action.id)
  }
}

const creating = computed(() => createMutation.isPending.value)
const sending = computed(() => sendMutation.isPending.value)
const accepting = computed(() => acceptMutation.isPending.value)
const pdfLoading = computed(() => pdfMutation.isPending.value)
</script>

<template>
  <div class="flex flex-col gap-4">
    <h2>Orçamentos</h2>
    <p class="text-[13px] text-muted">
      A timeline fica nesta venda. Gerar só a partir de rascunho com itens.
    </p>

    <PermissionGate v-if="canCreate" permission="budgets.create">
      <SurfaceCard>
        <div class="flex flex-col gap-3">
          <FormField label="Validade" html-for="budget-valid-until">
            <Input id="budget-valid-until" v-model="validUntil" type="date" />
          </FormField>
          <FormField label="Notas do orçamento" html-for="budget-notes">
            <Textarea id="budget-notes" v-model="notes" />
          </FormField>
          <Button :loading="creating" @click="createMutation.mutate()">
            Gerar orçamento
          </Button>
        </div>
      </SurfaceCard>
    </PermissionGate>

    <Banner v-if="isError" variant="danger" title="Não foi possível carregar os orçamentos" />

    <SurfaceCard v-else-if="isPending" :padding="false">
      <p class="px-5 py-4 text-[15px] text-muted">Carregando orçamentos…</p>
    </SurfaceCard>

    <SurfaceCard v-else-if="budgets.length === 0" :padding="false">
      <p class="px-5 py-4 text-[15px] text-muted">Nenhum orçamento nesta venda.</p>
    </SurfaceCard>

    <SurfaceCard v-else :padding="false">
      <div class="divide-y divide-border-divider px-5 py-2">
        <div v-for="budget in budgets" :key="budget.id" class="py-3">
          <ListCard
            :title="`Versão ${budget.version}`"
            :meta="budgetMeta(budget)"
            :badge="BUDGET_STATUS_LABELS[budget.status]"
            :badge-variant="BUDGET_STATUS_BADGE[budget.status]"
          />
          <div class="mt-2 flex flex-wrap gap-2">
            <PermissionGate v-if="budget.status === 'draft'" permission="budgets.update">
              <Button
                variant="secondary"
                :loading="sending"
                @click="sendMutation.mutate(budget.id)"
              >
                Enviar
              </Button>
            </PermissionGate>
            <PermissionGate v-if="budget.status === 'sent'" permission="budgets.convert">
              <Button
                :loading="accepting"
                @click="acceptMutation.mutate(budget.id)"
              >
                Aceitar
              </Button>
            </PermissionGate>
            <PermissionGate
              v-if="budget.status === 'draft' || budget.status === 'sent'"
              permission="budgets.update"
            >
              <Button variant="ghost" @click="pendingAction = { id: budget.id, kind: 'reject' }">
                Recusar
              </Button>
              <Button variant="ghost" @click="pendingAction = { id: budget.id, kind: 'expire' }">
                Expirar
              </Button>
            </PermissionGate>
            <PermissionGate permission="documents.generate">
              <Button
                variant="secondary"
                :loading="pdfLoading"
                @click="pdfMutation.mutate(budget)"
              >
                PDF
              </Button>
            </PermissionGate>
          </div>
        </div>
      </div>
    </SurfaceCard>

    <ConfirmDialog
      v-model:open="confirmOpen"
      :title="pendingAction?.kind === 'expire' ? 'Expirar este orçamento?' : 'Recusar este orçamento?'"
      description="A venda permanece em rascunho."
      :confirm-label="pendingAction?.kind === 'expire' ? 'Expirar' : 'Recusar'"
      @confirm="onConfirmPending"
    />
  </div>
</template>
