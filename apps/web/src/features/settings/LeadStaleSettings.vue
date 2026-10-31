<script setup lang="ts">
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
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

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()
const queryClient = useQueryClient()
const canEdit = computed(() => auth.can('clinics.branding') || auth.can('clinics.manage'))

const { data: settings, isPending, isError } = useClinicSettings()

const draft = ref<string | number>('')
const error = ref<string | undefined>()

watch(
  settings,
  (value) => {
    if (value) {
      draft.value = value.lead_stale_days
    }
  },
  { immediate: true },
)

const defaultDays = computed(() => settings.value?.lead_stale_days_default ?? 3)

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: () => {
    const raw = String(draft.value).trim()
    return updateClinicSettings({ lead_stale_days: raw === '' ? null : Number(raw) })
  },
  onSuccess: (value) => {
    error.value = undefined
    queryClient.setQueryData(CLINIC_SETTINGS_KEY, value)
    void queryClient.invalidateQueries({ queryKey: ['leads'] })
    toast.success('Configuração salva')
  },
  onError: (err) => {
    if (err instanceof ApiError && err.status === 422) {
      error.value = err.first('lead_stale_days')
      return
    }
    toast.error(err instanceof ApiError ? err.message : 'Não foi possível salvar.')
  },
})
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader
      stack-actions
      title="Leads parados"
      description="Quando um lead em aberto fica sem nenhum contato por alguns dias, ele aparece como parado."
    >
      <template #actions>
        <Button mobile-block variant="ghost" @click="router.push({ name: 'settings' })">
          Configurações
        </Button>
      </template>
    </PageHeader>

    <Banner v-if="!canEdit" variant="danger" title="Sem permissão">
      Só a administração da clínica altera esta configuração.
    </Banner>

    <Banner v-else-if="isError" variant="danger" title="Não foi possível carregar">
      Tente de novo em instantes.
    </Banner>

    <SurfaceCard v-else-if="isPending">
      <Skeleton class="h-16" />
    </SurfaceCard>

    <template v-else-if="settings">
      <SurfaceCard>
        <form class="flex flex-col gap-4" @submit.prevent="save()">
          <FormField
            label="Dias para considerar lead parado"
            html-for="lead-stale-days"
            :error="error"
            :hint="`Conta qualquer contato: comentário, mudança de status ou WhatsApp. Padrão: ${defaultDays} dias.`"
          >
            <Input id="lead-stale-days" v-model="draft" type="number" :invalid="!!error" />
          </FormField>
          <p class="text-[13px] text-muted">
            Leads que viraram paciente ou foram cancelados nunca ficam parados. Quem gerencia leads
            recebe um resumo por dia em Alertas.
          </p>
          <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button mobile-block variant="ghost" type="button" @click="draft = defaultDays">
              Restaurar padrão
            </Button>
            <Button mobile-block type="submit" :loading="saving">Salvar</Button>
          </div>
        </form>
      </SurfaceCard>
    </template>
  </div>
</template>
