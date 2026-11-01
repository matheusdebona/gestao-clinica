<script setup lang="ts">
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'
import Banner from '@/components/ui/Banner.vue'
import ActionBar from '@/components/ui/ActionBar.vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Textarea from '@/components/ui/Textarea.vue'
import { updateClinicSettings, type WhatsAppTemplateKey } from '@/features/settings/api'
import { CLINIC_SETTINGS_KEY, useClinicSettings } from '@/features/settings/useClinicSettings'
import { renderWhatsAppTemplate } from '@/lib/whatsapp'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'

const auth = useAuthStore()
const toast = useToastStore()
const queryClient = useQueryClient()
const canEdit = computed(() => auth.can('clinics.branding') || auth.can('clinics.manage'))

const { data: settings, isPending, isError } = useClinicSettings()

const sections: { key: WhatsAppTemplateKey; title: string; description: string }[] = [
  {
    key: 'appointment_confirmation',
    title: 'Confirmação de sessão',
    description: 'Usada no botão “Confirmar pelo WhatsApp” da agenda.',
  },
  {
    key: 'lead_follow_up',
    title: 'Contato com lead',
    description: 'Usada no botão “Chamar no WhatsApp” dos leads.',
  },
  {
    key: 'patient_return',
    title: 'Retorno do paciente',
    description: 'Usada no botão “Chamar no WhatsApp” da lista de Retornos.',
  },
  {
    key: 'budget_follow_up',
    title: 'Cobrança de orçamento',
    description: 'Usada no botão “Cobrar no WhatsApp” dos orçamentos esquecidos.',
  },
]

const drafts = ref<Record<WhatsAppTemplateKey, string>>({
  appointment_confirmation: '',
  lead_follow_up: '',
  patient_return: '',
  budget_follow_up: '',
})
const errors = ref<Partial<Record<WhatsAppTemplateKey, string>>>({})

watch(
  settings,
  (value) => {
    if (value) {
      drafts.value = { ...value.whatsapp_templates }
    }
  },
  { immediate: true },
)

const sample = computed(() => ({
  paciente: 'Camila',
  clinica: settings.value?.clinic_name || auth.clinicName || 'Clínica',
  data: '05/11',
  hora: '14:30',
  profissional: 'Dra. Paula',
  procedimento: 'Toxina botulínica',
  valor: 'R$ 1.500,00',
  usuario: auth.user?.name?.split(' ')[0] ?? 'Rita',
}))

function preview(key: WhatsAppTemplateKey): string {
  return renderWhatsAppTemplate(drafts.value[key] || settings.value?.whatsapp_template_defaults[key] || '', sample.value)
}

function restore(key: WhatsAppTemplateKey) {
  drafts.value[key] = settings.value?.whatsapp_template_defaults[key] ?? ''
}

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: () => updateClinicSettings({ whatsapp_templates: { ...drafts.value } }),
  onSuccess: (value) => {
    errors.value = {}
    queryClient.setQueryData(CLINIC_SETTINGS_KEY, value)
    toast.success('Mensagens salvas')
  },
  onError: (error) => {
    if (error instanceof ApiError && error.status === 422) {
      errors.value = Object.fromEntries(
        sections.map((section) => [section.key, error.first(`whatsapp_templates.${section.key}`)]),
      )
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível salvar.')
  },
})
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader
      :back="{ label: 'Configurações', to: { name: 'settings' } }"
      stack-actions
      title="Mensagens do WhatsApp"
      description="Textos que abrem prontos no WhatsApp. Você revisa antes de enviar."
    >
    </PageHeader>

    <Banner v-if="!canEdit" variant="danger" title="Sem permissão">
      Só a administração da clínica altera as mensagens.
    </Banner>

    <Banner v-else-if="isError" variant="danger" title="Não foi possível carregar">
      Tente de novo em instantes.
    </Banner>

    <SurfaceCard v-else-if="isPending">
      <Skeleton class="h-24" />
      <Skeleton class="mt-3 h-24" />
    </SurfaceCard>

    <template v-else-if="settings">
      <SurfaceCard v-for="section in sections" :key="section.key">
        <h2>{{ section.title }}</h2>
        <p class="mt-1 text-[13px] text-muted">{{ section.description }}</p>
        <div class="mt-4 flex flex-col gap-3">
          <FormField
            label="Mensagem"
            :hint="`Use: ${settings.whatsapp_placeholders[section.key].join(' ')}`"
            :error="errors[section.key]"
            :html-for="`wa-${section.key}`"
          >
            <template #default="{ invalid }">
              <Textarea
                :id="`wa-${section.key}`"
                v-model="drafts[section.key]"
                :rows="4"
                :invalid="invalid"
              />
            </template>
          </FormField>
          <div>
            <p class="text-[13px] font-medium text-muted">Prévia</p>
            <p class="mt-1 whitespace-pre-wrap text-[15px] text-body">{{ preview(section.key) }}</p>
          </div>
          <ActionBar>
            <template #negative>
              <Button mobile-block variant="ghost" type="button" @click="restore(section.key)">
                Restaurar padrão
              </Button>
            </template>
          </ActionBar>
        </div>
      </SurfaceCard>

      <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button mobile-block :loading="saving" @click="save()">Salvar mensagens</Button>
      </div>
    </template>
  </div>
</template>
