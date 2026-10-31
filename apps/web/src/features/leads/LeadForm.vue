<script setup lang="ts">
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { useForm } from 'vee-validate'
import { computed, ref, watch } from 'vue'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import AppDialog from '@/components/ui/AppDialog.vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import PhoneInput from '@/components/ui/PhoneInput.vue'
import Select from '@/components/ui/Select.vue'
import Textarea from '@/components/ui/Textarea.vue'
import { createClientOrigin } from '@/features/clients/api'
import { LEAD_CONVERTED_KEY } from '@/features/leads/labels'
import {
  emptyLeadForm,
  leadFormSchema,
  leadToForm,
  toLeadPayload,
} from '@/features/leads/schema'
import { useLeadCatalogs } from '@/features/leads/useLeadCatalogs'
import { useToastStore } from '@/stores/toast'
import type { ClientOrigin } from '@/types/client'
import type { Lead, LeadPayload } from '@/types/lead'
import { ApiError } from '@/types/user'

const props = defineProps<{
  lead?: Lead | null
  submitLabel?: string
  loading?: boolean
}>()

const emit = defineEmits<{
  submit: [payload: LeadPayload]
  cancel: []
}>()

const toast = useToastStore()
const queryClient = useQueryClient()
const { activeStatuses, activeOrigins } = useLeadCatalogs()

const originDialog = ref(false)
const newOriginName = ref('')
const originDialogError = ref('')
const extraOrigins = ref<ClientOrigin[]>([])

const { defineField, handleSubmit, errors, setErrors, resetForm, setFieldValue } = useForm({
  validationSchema: leadFormSchema,
  initialValues: emptyLeadForm(),
})

const [name, nameAttrs] = defineField('name')
const [phone, phoneAttrs] = defineField('phone')
const [originId] = defineField('client_origin_id')
const [statusId] = defineField('lead_status_id')
const [notes, notesAttrs] = defineField('notes')

const isConverted = computed(() => Boolean(props.lead?.client_id))

const originOptions = computed(() => {
  const list: ClientOrigin[] = [...extraOrigins.value, ...activeOrigins.value]
  const current = props.lead?.client_origin
  if (current && !list.some((origin) => origin.id === current.id)) {
    list.unshift(current)
  }
  const seen = new Set<number>()
  return list
    .filter((origin) => (seen.has(origin.id) ? false : (seen.add(origin.id), true)))
    .map((origin) => ({ value: String(origin.id), label: origin.name }))
})

const statusOptions = computed(() => {
  const list = activeStatuses.value.filter(
    (status) => isConverted.value || status.system_key !== LEAD_CONVERTED_KEY,
  )
  const current = props.lead?.status
  if (current && !list.some((status) => status.id === current.id)) {
    list.unshift(current)
  }
  return list.map((status) => ({ value: String(status.id), label: status.name }))
})

watch(
  () => props.lead,
  (lead) => {
    resetForm({ values: lead ? leadToForm(lead) : emptyLeadForm() })
  },
  { immediate: true },
)

// New lead: preselect the clinic default status once statuses load.
watch(
  activeStatuses,
  (list) => {
    if (props.lead || statusId.value) {
      return
    }
    const fallback = list.find((status) => status.is_default) ?? list[0]
    if (fallback) {
      setFieldValue('lead_status_id', String(fallback.id))
    }
  },
  { immediate: true },
)

const { mutate: createOriginMutate, isPending: creatingOrigin } = useMutation({
  mutationFn: () => createClientOrigin({ name: newOriginName.value.trim() }),
  onSuccess: async (origin) => {
    originDialog.value = false
    newOriginName.value = ''
    originDialogError.value = ''
    toast.success('Origem cadastrada')
    extraOrigins.value = [origin, ...extraOrigins.value]
    setFieldValue('client_origin_id', String(origin.id))
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

const onSubmit = handleSubmit((values) => {
  emit('submit', toLeadPayload(values))
})

defineExpose({ setErrors })
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
    <FormField label="Nome" :error="errors.name" html-for="lead-name">
      <template #default="{ invalid }">
        <Input
          id="lead-name"
          v-model="name"
          v-bind="nameAttrs"
          type="text"
          autocomplete="name"
          :invalid="invalid"
        />
      </template>
    </FormField>

    <FormField label="Telefone" :error="errors.phone" html-for="lead-phone">
      <template #default="{ invalid }">
        <PhoneInput id="lead-phone" v-model="phone" v-bind="phoneAttrs" :invalid="invalid" />
      </template>
    </FormField>

    <FormField label="Origem" :error="errors.client_origin_id" html-for="lead-origin">
      <template #default="{ invalid }">
        <Select
          id="lead-origin"
          v-model="originId"
          :options="originOptions"
          :invalid="invalid"
          placeholder="De onde veio"
        />
      </template>
    </FormField>
    <PermissionGate permission="client_origins.manage">
      <Button variant="ghost" type="button" @click="originDialog = true">Nova origem</Button>
    </PermissionGate>

    <FormField
      label="Status"
      :hint="isConverted ? 'Lead que virou paciente mantém este status.' : ''"
      :error="errors.lead_status_id"
      html-for="lead-status"
    >
      <template #default="{ invalid }">
        <Select
          id="lead-status"
          v-model="statusId"
          :options="statusOptions"
          :invalid="invalid"
          :disabled="isConverted"
        />
      </template>
    </FormField>

    <FormField
      label="Observação"
      hint="Ex.: restrição, interesse, por que avançou ou não."
      :error="errors.notes"
      html-for="lead-notes"
    >
      <template #default="{ invalid }">
        <Textarea id="lead-notes" v-model="notes" v-bind="notesAttrs" :invalid="invalid" />
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
    description="A origem vale para leads e pacientes."
  >
    <FormField label="Nome" :error="originDialogError" html-for="new-lead-origin-name">
      <template #default="{ invalid }">
        <Input id="new-lead-origin-name" v-model="newOriginName" type="text" :invalid="invalid" />
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
</template>
