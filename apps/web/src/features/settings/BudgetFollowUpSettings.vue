<script setup lang="ts">
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Badge from '@/components/ui/Badge.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { updateClinicSettings } from '@/features/settings/api'
import { CLINIC_SETTINGS_KEY, useClinicSettings } from '@/features/settings/useClinicSettings'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'

const MAX_TIERS = 5

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()
const queryClient = useQueryClient()
const canEdit = computed(() => auth.can('clinics.branding') || auth.can('clinics.manage'))

const { data: settings, isPending, isError } = useClinicSettings()

const drafts = ref<string[]>([])
const error = ref('')

watch(
  settings,
  (value) => {
    if (value) {
      drafts.value = value.budget_follow_up_days.map(String)
    }
  },
  { immediate: true },
)

function addTier() {
  const numbers = drafts.value.map(Number).filter((n) => Number.isInteger(n) && n > 0)
  const next = numbers.length ? Math.min(Math.max(...numbers) * 2, 365) : 2
  drafts.value.push(String(next))
}

function removeTier(index: number) {
  drafts.value.splice(index, 1)
}

function restore() {
  drafts.value = (settings.value?.budget_follow_up_days_default ?? [2, 5, 10]).map(String)
  error.value = ''
}

function parsed(): number[] | null {
  const days = drafts.value.map((value) => value.trim()).filter((value) => value !== '')
  const numbers = days.map(Number)
  if (numbers.some((n) => !Number.isInteger(n) || n < 1 || n > 365)) {
    error.value = 'Informe de 1 a 365 dias em cada prazo.'
    return null
  }
  if (new Set(numbers).size !== numbers.length) {
    error.value = 'Não repita o mesmo prazo.'
    return null
  }
  return [...numbers].sort((a, b) => a - b)
}

const { mutate, isPending: saving } = useMutation({
  mutationFn: (days: number[]) => updateClinicSettings({ budget_follow_up_days: days }),
  onSuccess: async (data) => {
    queryClient.setQueryData(CLINIC_SETTINGS_KEY, data)
    await queryClient.invalidateQueries({ queryKey: ['budgets'] })
    toast.success('Prazos salvos')
  },
  onError: (err) => {
    error.value =
      err instanceof ApiError
        ? err.first('budget_follow_up_days') || err.message
        : 'Não foi possível salvar.'
  },
})

function save() {
  error.value = ''
  const days = parsed()
  if (days) {
    mutate(days)
  }
}

function tierHint(index: number): string {
  return index === drafts.value.length - 1 && drafts.value.length > 1
    ? 'Último prazo: aparece em vermelho.'
    : ''
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader
      stack-actions
      title="Orçamentos esquecidos"
      description="Prazos sem resposta para um orçamento enviado virar alerta. Quem cuida de orçamentos é avisado às 08:00 e vê a fila no Resumo do dia."
    >
      <template #actions>
        <Button mobile-block variant="ghost" @click="router.push({ name: 'settings' })">
          Configurações
        </Button>
      </template>
    </PageHeader>

    <Banner v-if="!canEdit" variant="danger" title="Sem permissão">
      Só a administração da clínica altera os prazos de orçamentos esquecidos.
    </Banner>

    <Banner v-else-if="isError" variant="danger" title="Não foi possível carregar">
      Tente de novo em instantes.
    </Banner>

    <SurfaceCard v-else-if="isPending">
      <Skeleton class="h-12" />
      <Skeleton class="mt-3 h-12" />
    </SurfaceCard>

    <template v-else-if="settings">
      <SurfaceCard>
        <h2>Prazos (dias sem resposta)</h2>
        <p class="mt-1 text-[13px] text-muted">
          Ex.: 2, 5 e 10 dias. Sem nenhum prazo, nenhum orçamento vira alerta.
        </p>
        <div class="mt-4 flex flex-col gap-3">
          <FormField
            v-for="(_, index) in drafts"
            :key="index"
            :label="`${index + 1}º prazo`"
            :hint="tierHint(index)"
            :html-for="`budget-tier-${index}`"
          >
            <template #default="{ invalid }">
              <div class="flex items-center gap-2">
                <div class="min-w-0 flex-1">
                  <Input
                    :id="`budget-tier-${index}`"
                    v-model="drafts[index]"
                    type="number"
                    :invalid="invalid || Boolean(error)"
                  />
                </div>
                <Badge
                  :variant="index === drafts.length - 1 && drafts.length > 1 ? 'danger' : 'warning'"
                >
                  {{ drafts[index] || '?' }}+ dias
                </Badge>
                <Button variant="ghost" type="button" @click="removeTier(index)">Remover</Button>
              </div>
            </template>
          </FormField>
          <p v-if="error" class="text-[13px] text-danger">{{ error }}</p>
          <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
            <Button mobile-block variant="ghost" type="button" @click="restore">
              Restaurar padrão
            </Button>
            <Button
              mobile-block
              variant="secondary"
              type="button"
              :disabled="drafts.length >= MAX_TIERS"
              @click="addTier"
            >
              Novo prazo
            </Button>
          </div>
        </div>
      </SurfaceCard>

      <p class="text-[13px] text-muted">
        Conta como esquecido: orçamento enviado que não foi aceito, recusado nem expirou (pela
        situação ou pela validade). Cada prazo pede uma cobrança; usar “Cobrar no WhatsApp” tira o
        orçamento da fila até o próximo prazo.
      </p>

      <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button mobile-block :loading="saving" @click="save">Salvar prazos</Button>
      </div>
    </template>
  </div>
</template>
