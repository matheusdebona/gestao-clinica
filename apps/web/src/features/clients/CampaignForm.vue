<script setup lang="ts">
import { useForm } from 'vee-validate'
import { computed, watch } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Switch from '@/components/ui/Switch.vue'
import { listClientOrigins } from '@/features/clients/api'
import { campaignFormSchema } from '@/features/clients/schema'
import { useAuthStore } from '@/stores/auth'
import type { Campaign, CampaignPayload } from '@/types/client'

const props = defineProps<{
  campaign?: Campaign | null
  initialOriginId?: string
  submitLabel?: string
  loading?: boolean
}>()

const emit = defineEmits<{
  submit: [payload: CampaignPayload]
  cancel: []
}>()

const auth = useAuthStore()

const { defineField, handleSubmit, errors, setErrors, resetForm } = useForm({
  validationSchema: campaignFormSchema,
  initialValues: { client_origin_id: '', name: '', is_active: true },
})

const [originId] = defineField('client_origin_id')
const [name, nameAttrs] = defineField('name')
const [isActive] = defineField('is_active')

const originsQuery = useQuery({
  queryKey: ['client-origins', 'active'],
  queryFn: () => listClientOrigins({ active_only: true, page: 1 }),
  enabled: computed(
    () =>
      auth.can('client_origins.manage') ||
      auth.can('campaigns.manage') ||
      auth.can('clients.create') ||
      auth.can('clients.update'),
  ),
})

const originOptions = computed(() => {
  const list = [...(originsQuery.data.value?.data ?? [])]
  const current = props.campaign?.client_origin
  if (current && !list.some((origin) => origin.id === current.id)) {
    list.unshift(current)
  }
  return list.map((origin) => ({ value: String(origin.id), label: origin.name }))
})

watch(
  () => [props.campaign, props.initialOriginId] as const,
  ([campaign, initialOriginId]) => {
    resetForm({
      values: {
        client_origin_id: campaign
          ? String(campaign.client_origin_id)
          : (initialOriginId ?? ''),
        name: campaign?.name ?? '',
        is_active: campaign?.is_active ?? true,
      },
    })
  },
  { immediate: true },
)

const onSubmit = handleSubmit((values) => {
  emit('submit', {
    client_origin_id: Number(values.client_origin_id),
    name: values.name.trim(),
    is_active: values.is_active,
  })
})

defineExpose({ setErrors })
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
    <FormField label="Origem" :error="errors.client_origin_id" html-for="campaign-origin">
      <template #default="{ invalid }">
        <Select
          id="campaign-origin"
          v-model="originId"
          :options="originOptions"
          placeholder="Selecionar origem"
          :invalid="invalid"
        />
      </template>
    </FormField>
    <FormField label="Nome" :error="errors.name" html-for="campaign-name">
      <template #default="{ invalid }">
        <Input id="campaign-name" v-model="name" v-bind="nameAttrs" type="text" :invalid="invalid" />
      </template>
    </FormField>
    <Switch v-model="isActive" label="Ativa" />
    <div class="mt-2 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
      <Button variant="ghost" type="button" :disabled="loading" @click="emit('cancel')">Cancelar</Button>
      <Button type="submit" :loading="loading">{{ submitLabel ?? 'Salvar' }}</Button>
    </div>
  </form>
</template>
