<script setup lang="ts">
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { useForm } from 'vee-validate'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import AppDialog from '@/components/ui/AppDialog.vue'
import Banner from '@/components/ui/Banner.vue'
import ActionBar from '@/components/ui/ActionBar.vue'
import Button from '@/components/ui/Button.vue'
import Checkbox from '@/components/ui/Checkbox.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import PhoneInput from '@/components/ui/PhoneInput.vue'
import Select from '@/components/ui/Select.vue'
import Textarea from '@/components/ui/Textarea.vue'
import { createClientOrigin } from '@/features/clients/api'
import { checkLeadPhone } from '@/features/leads/api'
import { LEAD_CANCELLED_KEY, LEAD_CONVERTED_KEY } from '@/features/leads/labels'
import { emptyLeadForm, leadFormSchema, leadToForm, toLeadPayload } from '@/features/leads/schema'
import { useLeadCatalogs } from '@/features/leads/useLeadCatalogs'
import { useToastStore } from '@/stores/toast'
import type { ClientOrigin } from '@/types/client'
import type { Lead, LeadPayload, LeadPhoneConflict } from '@/types/lead'
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

const router = useRouter()
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
const alreadyApproached = ref(false)

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
    (status) =>
      status.system_key !== LEAD_CANCELLED_KEY &&
      (isConverted.value || status.system_key !== LEAD_CONVERTED_KEY),
  )
  const current = props.lead?.status
  if (current && !list.some((status) => status.id === current.id)) {
    list.unshift(current)
  }
  return list.map((status) => ({
    value: String(status.id),
    label: status.name,
  }))
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

// Duplicate phone: checked live (debounced) and again by the API on save.
const conflict = ref<LeadPhoneConflict | null>(null)
const checkingPhone = ref(false)
let phoneTimer: ReturnType<typeof setTimeout> | undefined
let phoneRequest = 0

function phoneDigits(value: unknown): string {
  return String(value ?? '').replace(/\D+/g, '')
}

watch(phone, (value) => {
  clearTimeout(phoneTimer)
  const digits = phoneDigits(value)
  const unchanged = props.lead && digits === phoneDigits(props.lead.phone)
  if (digits.length < 10 || unchanged) {
    conflict.value = null
    checkingPhone.value = false
    return
  }
  checkingPhone.value = true
  const request = ++phoneRequest
  phoneTimer = setTimeout(async () => {
    try {
      const result = await checkLeadPhone(digits, props.lead?.id)
      if (request === phoneRequest) {
        conflict.value = result
      }
    } catch {
      // Network hiccup: the API still validates on save.
    } finally {
      if (request === phoneRequest) {
        checkingPhone.value = false
      }
    }
  }, 400)
})

onBeforeUnmount(() => clearTimeout(phoneTimer))

function openConflict() {
  const current = conflict.value
  if (!current) {
    return
  }
  if (current.client) {
    void router.push({
      name: 'clients-show',
      params: { id: String(current.client.id) },
    })
    return
  }
  if (current.lead) {
    void router.push({
      name: 'leads-show',
      params: { id: String(current.lead.id) },
    })
  }
}

function setDuplicate(value: LeadPhoneConflict | null) {
  conflict.value = value
}

const onSubmit = handleSubmit((values) => {
  if (conflict.value) {
    return
  }
  emit('submit', {
    ...toLeadPayload(values),
    ...(props.lead ? {} : { already_approached: alreadyApproached.value }),
  })
})

defineExpose({ setErrors, setDuplicate })
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
        <PhoneInput
          id="lead-phone"
          v-model="phone"
          v-bind="phoneAttrs"
          :invalid="invalid || Boolean(conflict)"
        />
      </template>
    </FormField>

    <Banner
      v-if="conflict"
      variant="danger"
      :title="conflict.type === 'patient' ? 'Já é paciente' : 'Telefone já cadastrado'"
    >
      <p>{{ conflict.message }}</p>
      <div class="mt-2">
        <Button mobile-block variant="secondary" type="button" @click="openConflict">
          {{
            conflict.client
              ? `Abrir ${conflict.client.name}`
              : `Abrir lead ${conflict.lead?.name ?? ''}`
          }}
        </Button>
      </div>
    </Banner>

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
      <Button mobile-block variant="ghost" type="button" @click="originDialog = true"
        >Nova origem</Button
      >
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

    <Checkbox
      v-if="!lead"
      id="lead-already-approached"
      v-model="alreadyApproached"
      label="Já abordei este lead (conta como primeira abordagem)"
    />

    <ActionBar class="mt-2">
      <template #negative>
        <Button
          mobile-block
          variant="ghost"
          type="button"
          :disabled="loading"
          @click="emit('cancel')"
        >
          Cancelar
        </Button>
      </template>
      <Button
        mobile-block
        type="submit"
        :loading="loading"
        :disabled="Boolean(conflict) || checkingPhone"
      >
        {{ submitLabel ?? 'Salvar' }}
      </Button>
    </ActionBar>
  </form>

  <AppDialog
    stack-footer
    v-model:open="originDialog"
    title="Nova origem"
    description="A origem vale para leads e pacientes."
  >
    <FormField label="Nome" :error="originDialogError" html-for="new-lead-origin-name">
      <template #default="{ invalid }">
        <Input id="new-lead-origin-name" v-model="newOriginName" type="text" :invalid="invalid" />
      </template>
    </FormField>
    <template #footer-negative>
      <Button mobile-block variant="ghost" type="button" @click="originDialog = false"
        >Cancelar</Button
      >
    </template>
    <template #footer>
      <Button
        mobile-block
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
