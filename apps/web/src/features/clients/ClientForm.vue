<script setup lang="ts">
import { useForm } from 'vee-validate'
import { computed, nextTick, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import AppDialog from '@/components/ui/AppDialog.vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import PhoneInput from '@/components/ui/PhoneInput.vue'
import Select from '@/components/ui/Select.vue'
import Textarea from '@/components/ui/Textarea.vue'
import AttributionShortcuts from '@/features/clients/AttributionShortcuts.vue'
import {
  createCampaign,
  createClientOrigin,
  listCampaigns,
  listClientOrigins,
} from '@/features/clients/api'
import {
  NONE_VALUE,
  clientFormSchema,
  emptyClientForm,
  toClientPayload,
  type ClientFormValues,
} from '@/features/clients/schema'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { Campaign, Client, ClientOrigin, ClientPayload } from '@/types/client'
import type { Paginated } from '@/types/pagination'
import { ApiError } from '@/types/user'

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
const toast = useToastStore()
const queryClient = useQueryClient()

const canLoadOrigins = computed(
  () =>
    auth.can('client_origins.manage') ||
    auth.can('clients.create') ||
    auth.can('clients.update'),
)
const canLoadCampaigns = computed(
  () =>
    auth.can('campaigns.manage') ||
    auth.can('clients.create') ||
    auth.can('clients.update'),
)

const originDialog = ref(false)
const campaignDialog = ref(false)
const newOriginName = ref('')
const newCampaignName = ref('')
const originDialogError = ref('')
const campaignDialogError = ref('')
const extraOrigins = ref<ClientOrigin[]>([])
const extraCampaigns = ref<Campaign[]>([])

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
  queryKey: ['client-origins', 'active'],
  queryFn: () => listClientOrigins({ active_only: true, page: 1 }),
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
  queryKey: ['campaigns', 'by-origin', selectedOriginId],
  queryFn: () =>
    listCampaigns({
      client_origin_id: selectedOriginId.value ?? undefined,
      active_only: true,
      page: 1,
    }),
  enabled: computed(() => canLoadCampaigns.value && selectedOriginId.value !== null),
})

function withCurrentOption<T extends { id: number }>(list: T[], current: T | null | undefined): T[] {
  const merged: T[] = []
  const seen = new Set<number>()
  for (const item of list) {
    if (seen.has(item.id)) {
      continue
    }
    seen.add(item.id)
    merged.push(item)
  }
  if (current && !seen.has(current.id)) {
    merged.unshift(current)
  }
  return merged
}

const originOptions = computed(() => [
  { value: NONE_VALUE, label: 'Nenhuma' },
  ...withCurrentOption(
    [...extraOrigins.value, ...(originsQuery.data.value?.data ?? [])],
    props.client?.client_origin ?? null,
  ).map((origin) => ({
    value: String(origin.id),
    label: origin.name,
  })),
])

const campaignOptions = computed(() => [
  { value: NONE_VALUE, label: 'Nenhuma' },
  ...withCurrentOption(
    [
      ...extraCampaigns.value.filter((campaign) => campaign.client_origin_id === selectedOriginId.value),
      ...(campaignsQuery.data.value?.data ?? []),
    ],
    props.client?.campaign && props.client.campaign.client_origin_id === selectedOriginId.value
      ? props.client.campaign
      : null,
  ).map((campaign) => ({
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

function prependCatalogItem<T extends { id: number }>(old: Paginated<T> | undefined, item: T): Paginated<T> {
  if (!old) {
    return {
      data: [item],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    }
  }
  if (old.data.some((row) => row.id === item.id)) {
    return old
  }
  return {
    ...old,
    data: [item, ...old.data],
    meta: { ...old.meta, total: old.meta.total + 1 },
  }
}

function rememberCatalogItem<T extends { id: number }>(list: T[], item: T): T[] {
  if (list.some((row) => row.id === item.id)) {
    return list
  }
  return [item, ...list]
}

function onOriginUpdate(value: string) {
  if (value === originId.value) {
    return
  }
  setFieldValue('client_origin_id', value)
  setFieldValue('campaign_id', NONE_VALUE)
}

function openCampaignDialog() {
  if (!selectedOriginId.value) {
    toast.info('Selecione a origem primeiro.')
    return
  }
  campaignDialogError.value = ''
  newCampaignName.value = ''
  campaignDialog.value = true
}

const { mutate: createOriginMutate, isPending: creatingOrigin } = useMutation({
  mutationFn: () => createClientOrigin({ name: newOriginName.value.trim() }),
  onSuccess: async (origin) => {
    originDialog.value = false
    newOriginName.value = ''
    originDialogError.value = ''
    toast.success('Origem cadastrada')
    extraOrigins.value = rememberCatalogItem(extraOrigins.value, origin)
    queryClient.setQueryData(['client-origins', 'active'], (old: Paginated<ClientOrigin> | undefined) =>
      prependCatalogItem(old, origin),
    )
    setFieldValue('client_origin_id', String(origin.id))
    setFieldValue('campaign_id', NONE_VALUE)
    await nextTick()
    await queryClient.invalidateQueries({ queryKey: ['client-origins'] })
  },
  onError: (error) => {
    if (error instanceof ApiError && error.status === 422) {
      originDialogError.value = error.first('name') || error.message
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível cadastrar a origem.')
  },
})

const { mutate: createCampaignMutate, isPending: creatingCampaign } = useMutation({
  mutationFn: () =>
    createCampaign({
      name: newCampaignName.value.trim(),
      client_origin_id: selectedOriginId.value as number,
    }),
  onSuccess: async (campaign) => {
    campaignDialog.value = false
    newCampaignName.value = ''
    campaignDialogError.value = ''
    toast.success('Campanha cadastrada')
    extraCampaigns.value = rememberCatalogItem(extraCampaigns.value, campaign)
    queryClient.setQueryData(
      ['campaigns', 'by-origin', selectedOriginId.value],
      (old: Paginated<Campaign> | undefined) => prependCatalogItem(old, campaign),
    )
    setFieldValue('campaign_id', String(campaign.id))
    await nextTick()
    await queryClient.invalidateQueries({ queryKey: ['campaigns'] })
  },
  onError: (error) => {
    if (error instanceof ApiError && error.status === 422) {
      campaignDialogError.value =
        error.first('name') || error.first('client_origin_id') || error.message
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível cadastrar a campanha.')
  },
})

const onSubmit = handleSubmit((formValues) => {
  emit('submit', toClientPayload(formValues))
})

defineExpose({ setErrors })
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
    <AttributionShortcuts />

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
        <PhoneInput
          id="client-whatsapp"
          v-model="whatsapp"
          v-bind="whatsappAttrs"
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
    <PermissionGate permission="client_origins.manage">
      <Button variant="ghost" type="button" @click="originDialog = true">Nova origem</Button>
    </PermissionGate>

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
    <PermissionGate permission="campaigns.manage">
      <Button variant="ghost" type="button" @click="openCampaignDialog">Nova campanha desta origem</Button>
    </PermissionGate>

    <FormField
      label="Valor da avaliação"
      hint="Opcional."
      :error="errors.initial_consultation_amount"
      html-for="client-consultation"
    >
      <template #default="{ invalid }">
        <MoneyInput
          id="client-consultation"
          v-model="consultation"
          v-bind="consultationAttrs"
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

  <AppDialog
    v-model:open="originDialog"
    title="Nova origem"
    description="A origem fica disponível no cadastro de pacientes."
  >
    <FormField label="Nome" :error="originDialogError" html-for="new-origin-name">
      <template #default="{ invalid }">
        <Input id="new-origin-name" v-model="newOriginName" type="text" :invalid="invalid" />
      </template>
    </FormField>
    <template #footer>
      <Button variant="ghost" type="button" @click="originDialog = false">Cancelar</Button>
      <Button
        type="button"
        :loading="creatingOrigin"
        :disabled="!newOriginName.trim()"
        @click="createOriginMutate()"
      >
        Cadastrar
      </Button>
    </template>
  </AppDialog>

  <AppDialog
    v-model:open="campaignDialog"
    title="Nova campanha"
    description="A campanha fica ligada à origem selecionada."
  >
    <FormField label="Nome" :error="campaignDialogError" html-for="new-campaign-name">
      <template #default="{ invalid }">
        <Input id="new-campaign-name" v-model="newCampaignName" type="text" :invalid="invalid" />
      </template>
    </FormField>
    <template #footer>
      <Button variant="ghost" type="button" @click="campaignDialog = false">Cancelar</Button>
      <Button
        type="button"
        :loading="creatingCampaign"
        :disabled="!newCampaignName.trim()"
        @click="createCampaignMutate()"
      >
        Cadastrar
      </Button>
    </template>
  </AppDialog>
</template>
