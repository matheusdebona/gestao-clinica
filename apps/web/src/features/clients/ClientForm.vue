<script setup lang="ts">
import { useForm } from 'vee-validate'
import { computed, watch } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Textarea from '@/components/ui/Textarea.vue'
import { listCampaigns, listClientOrigins } from '@/features/clients/api'
import {
  NONE_VALUE,
  clientFormSchema,
  emptyClientForm,
  toClientPayload,
  type ClientFormValues,
} from '@/features/clients/schema'
import { useAuthStore } from '@/stores/auth'
import type { Client, ClientPayload } from '@/types/client'

const props = defineProps<{
  client?: Client | null
  submitLabel?: string
  loading?: boolean
}>()

const emit = defineEmits<{
  submit: [payload: ClientPayload]
  cancel: []
}>()

const auth = useAuthStore()
const canLoadOrigins = computed(() => auth.can('client_origins.manage'))
const canLoadCampaigns = computed(() => auth.can('campaigns.manage'))

const { defineField, handleSubmit, errors, setErrors, resetForm, setFieldValue } = useForm({
  validationSchema: clientFormSchema,
  initialValues: emptyClientForm(),
})

const [name, nameAttrs] = defineField('name')
const [whatsapp, whatsappAttrs] = defineField('whatsapp')
const [notes, notesAttrs] = defineField('notes')
const [mainPains, mainPainsAttrs] = defineField('main_pains')
const [duration, durationAttrs] = defineField('service_duration_minutes')
const [originId] = defineField('client_origin_id')
const [campaignId, campaignAttrs] = defineField('campaign_id')
const [consultation, consultationAttrs] = defineField('initial_consultation_amount')

const originsQuery = useQuery({
  queryKey: ['client-origins'],
  queryFn: listClientOrigins,
  enabled: canLoadOrigins,
})

const selectedOriginId = computed(() => {
  if (!originId.value || originId.value === NONE_VALUE) {
    return null
  }
  const parsed = Number(originId.value)
  return Number.isInteger(parsed) ? parsed : null
})

const campaignsQuery = useQuery({
  queryKey: ['campaigns', selectedOriginId],
  queryFn: () => listCampaigns(selectedOriginId.value),
  enabled: computed(() => canLoadCampaigns.value && selectedOriginId.value !== null),
})

const originOptions = computed(() => [
  { value: NONE_VALUE, label: 'Nenhuma' },
  ...(originsQuery.data.value ?? []).map((origin) => ({
    value: String(origin.id),
    label: origin.name,
  })),
])

const campaignOptions = computed(() => [
  { value: NONE_VALUE, label: 'Nenhuma' },
  ...(campaignsQuery.data.value ?? []).map((campaign) => ({
    value: String(campaign.id),
    label: campaign.name,
  })),
])

watch(
  () => props.client,
  (client) => {
    if (!client) {
      resetForm({ values: emptyClientForm() })
      return
    }
    resetForm({
      values: {
        name: client.name,
        whatsapp: client.whatsapp,
        notes: client.notes ?? '',
        main_pains: client.main_pains ?? '',
        service_duration_minutes:
          client.service_duration_minutes === null ? '' : String(client.service_duration_minutes),
        client_origin_id: client.client_origin_id ? String(client.client_origin_id) : NONE_VALUE,
        campaign_id: client.campaign_id ? String(client.campaign_id) : NONE_VALUE,
        initial_consultation_amount: client.initial_consultation_amount ?? '',
      } satisfies ClientFormValues,
    })
  },
  { immediate: true },
)

function onOriginUpdate(value: string) {
  if (value === originId.value) {
    return
  }
  setFieldValue('client_origin_id', value)
  setFieldValue('campaign_id', NONE_VALUE)
}

const onSubmit = handleSubmit((formValues) => {
  emit('submit', toClientPayload(formValues))
})

defineExpose({ setErrors })
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
    <FormField label="Nome" :error="errors.name" html-for="client-name">
      <template #default="{ invalid }">
        <Input
          id="client-name"
          v-model="name"
          v-bind="nameAttrs"
          type="text"
          autocomplete="name"
          :invalid="invalid"
        />
      </template>
    </FormField>

    <FormField label="WhatsApp" :error="errors.whatsapp" html-for="client-whatsapp">
      <template #default="{ invalid }">
        <Input
          id="client-whatsapp"
          v-model="whatsapp"
          v-bind="whatsappAttrs"
          type="tel"
          autocomplete="tel"
          :invalid="invalid"
        />
      </template>
    </FormField>

    <FormField
      v-if="canLoadOrigins"
      label="Origem"
      :error="errors.client_origin_id"
      html-for="client-origin"
    >
      <template #default="{ invalid }">
        <Select
          id="client-origin"
          :model-value="originId"
          :options="originOptions"
          :invalid="invalid"
          @update:model-value="onOriginUpdate"
        />
      </template>
    </FormField>

    <FormField
      v-if="canLoadCampaigns && selectedOriginId"
      label="Campanha"
      :error="errors.campaign_id"
      html-for="client-campaign"
    >
      <template #default="{ invalid }">
        <Select
          id="client-campaign"
          v-model="campaignId"
          v-bind="campaignAttrs"
          :options="campaignOptions"
          :invalid="invalid"
        />
      </template>
    </FormField>

    <FormField
      label="Valor da avaliação"
      hint="Opcional. Use ponto ou vírgula decimal."
      :error="errors.initial_consultation_amount"
      html-for="client-consultation"
    >
      <template #default="{ invalid }">
        <Input
          id="client-consultation"
          v-model="consultation"
          v-bind="consultationAttrs"
          type="text"
          inputmode="decimal"
          :invalid="invalid"
        />
      </template>
    </FormField>

    <FormField
      label="Duração do atendimento (min)"
      :error="errors.service_duration_minutes"
      html-for="client-duration"
    >
      <template #default="{ invalid }">
        <Input
          id="client-duration"
          v-model="duration"
          v-bind="durationAttrs"
          type="number"
          :invalid="invalid"
        />
      </template>
    </FormField>

    <FormField label="Queixas principais" :error="errors.main_pains" html-for="client-pains">
      <template #default="{ invalid }">
        <Textarea id="client-pains" v-model="mainPains" v-bind="mainPainsAttrs" :invalid="invalid" />
      </template>
    </FormField>

    <FormField label="Notas" :error="errors.notes" html-for="client-notes">
      <template #default="{ invalid }">
        <Textarea id="client-notes" v-model="notes" v-bind="notesAttrs" :invalid="invalid" />
      </template>
    </FormField>

    <div class="mt-2 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
      <Button variant="ghost" type="button" :disabled="loading" @click="emit('cancel')">
        Cancelar
      </Button>
      <Button type="submit" :loading="loading">
        {{ submitLabel ?? 'Salvar' }}
      </Button>
    </div>
  </form>
</template>
