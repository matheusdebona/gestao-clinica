<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'
import AppDialog from '@/components/ui/AppDialog.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import DateTimePicker from '@/components/ui/DateTimePicker.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import PhoneInput from '@/components/ui/PhoneInput.vue'
import Radio from '@/components/ui/Radio.vue'
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Textarea from '@/components/ui/Textarea.vue'
import { listProfessionals } from '@/features/appointments/api'
import { datetimeLocalToIso } from '@/features/appointments/schema'
import { convertLead, listLeadMatchingClients } from '@/features/leads/api'
import { formatPhoneBR, phoneDigits } from '@/lib/masks'
import { useToastStore } from '@/stores/toast'
import type { Lead, LeadConvertResult } from '@/types/lead'
import { ApiError } from '@/types/user'

const NEW_CLIENT = 'new'

const open = defineModel<boolean>('open', { default: false })

const props = defineProps<{
  lead: Lead
}>()

const emit = defineEmits<{
  converted: [result: LeadConvertResult]
}>()

const toast = useToastStore()
const queryClient = useQueryClient()

const professionalId = ref('')
const scheduledAt = ref('')
const duration = ref('')
const notes = ref('')
const phone = ref('')
const clientChoice = ref(NEW_CLIENT)
const errors = ref<Record<string, string>>({})

const leadId = computed(() => props.lead.id)
/** Agendar exige telefone: lead sem número informa aqui (salvo no lead e no paciente). */
const needsPhone = computed(() => !props.lead.phone)

const professionalsQuery = useQuery({
  queryKey: ['professionals'],
  queryFn: listProfessionals,
  enabled: open,
})

const matchesQuery = useQuery({
  queryKey: ['leads', 'matching-clients', leadId],
  queryFn: () => listLeadMatchingClients(leadId.value),
  enabled: open,
})

const professionalOptions = computed(() =>
  (professionalsQuery.data.value ?? []).map((user) => ({
    value: String(user.id),
    label: user.name,
  })),
)

const matches = computed(() => matchesQuery.data.value ?? [])

const clientOptions = computed(() => [
  ...matches.value.map((client) => ({
    value: String(client.id),
    label: `Vincular a ${client.name} · ${formatPhoneBR(client.whatsapp) || client.whatsapp}${client.is_active ? '' : ' (inativo)'}`,
  })),
  { value: NEW_CLIENT, label: `Criar novo paciente “${props.lead.name}”` },
])

watch(open, (isOpen) => {
  if (!isOpen) {
    return
  }
  errors.value = {}
  scheduledAt.value = ''
  duration.value = ''
  notes.value = ''
  phone.value = ''
  clientChoice.value = NEW_CLIENT
})

// Suggest linking when the phone already belongs to a patient.
watch(matches, (list) => {
  if (list.length && clientChoice.value === NEW_CLIENT) {
    clientChoice.value = String(list[0].id)
  }
})

watch(professionalOptions, (options) => {
  if (!professionalId.value && options.length === 1) {
    professionalId.value = options[0].value
  }
})

function validate(): boolean {
  const next: Record<string, string> = {}
  if (needsPhone.value) {
    const digits = phoneDigits(phone.value)
    if (!digits) {
      next.phone = 'Informe o telefone do paciente para agendar.'
    } else if (digits.length !== 10 && digits.length !== 11) {
      next.phone = 'Informe DDD e número.'
    }
  }
  if (!professionalId.value) {
    next.professional_user_id = 'Selecione o profissional.'
  }
  if (!scheduledAt.value) {
    next.scheduled_at = 'Informe data e hora.'
  }
  if (duration.value.trim()) {
    const parsed = Number(duration.value)
    if (!Number.isInteger(parsed) || parsed < 1 || parsed > 1440) {
      next.duration_minutes = 'Informe minutos entre 1 e 1440.'
    }
  }
  errors.value = next
  return Object.keys(next).length === 0
}

const { mutate, isPending } = useMutation({
  mutationFn: () => {
    const linking = clientChoice.value !== NEW_CLIENT
    return convertLead(props.lead.id, {
      professional_user_id: Number(professionalId.value),
      scheduled_at: datetimeLocalToIso(scheduledAt.value),
      duration_minutes: duration.value.trim() ? Number(duration.value) : null,
      notes: notes.value.trim() || null,
      client_id: linking ? Number(clientChoice.value) : null,
      create_new_client: !linking,
      ...(needsPhone.value ? { phone: phoneDigits(phone.value) } : {}),
    })
  },
  onSuccess: async (result) => {
    toast.success(
      result.client_created
        ? 'Lead virou paciente e avaliação agendada'
        : 'Lead vinculado ao paciente e avaliação agendada',
    )
    open.value = false
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['leads'] }),
      queryClient.invalidateQueries({ queryKey: ['clients'] }),
      queryClient.invalidateQueries({ queryKey: ['appointments'] }),
    ])
    emit('converted', result)
  },
  onError: (error) => {
    if (error instanceof ApiError && error.status === 422) {
      const mapped: Record<string, string> = {}
      for (const [field, messages] of Object.entries(error.errors)) {
        mapped[field] = messages[0] ?? error.message
      }
      errors.value = Object.keys(mapped).length ? mapped : { lead: error.message }
      if (mapped.client_id) {
        // O telefone informado já ficou salvo no lead: atualiza o lead e mostra o paciente com o mesmo número.
        void queryClient.invalidateQueries({ queryKey: ['leads'] })
        void matchesQuery.refetch()
      }
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível agendar.')
  },
})

function onConfirm() {
  if (validate()) {
    mutate()
  }
}
</script>

<template>
  <AppDialog
    v-model:open="open"
    size="lg"
    title="Agendar avaliação"
    description="Ao confirmar, o lead vira paciente (com nome, telefone, origem e histórico) e a avaliação entra na agenda."
  >
    <div class="grid gap-4 md:grid-cols-2">
      <Banner v-if="errors.lead" variant="danger" class="md:col-span-2">{{ errors.lead }}</Banner>

      <Skeleton v-if="matchesQuery.isPending.value" class="h-11 md:col-span-2" />
      <FormField
        v-else-if="matches.length"
        label="Já existe paciente com este telefone"
        :error="errors.client_id"
        class="md:col-span-2"
      >
        <Radio v-model="clientChoice" name="lead-convert-client" :options="clientOptions" />
      </FormField>

      <FormField
        v-if="needsPhone"
        label="Telefone do paciente"
        hint="Este lead ainda não tem telefone. Para agendar ele é obrigatório: fica salvo no lead e no cadastro do paciente."
        tip-hint
        :error="errors.phone"
        html-for="lead-convert-phone"
      >
        <template #default="{ invalid }">
          <PhoneInput id="lead-convert-phone" v-model="phone" :invalid="invalid" />
        </template>
      </FormField>

      <FormField
        label="Profissional"
        :error="errors.professional_user_id"
        html-for="lead-convert-pro"
      >
        <template #default="{ invalid }">
          <Select
            id="lead-convert-pro"
            v-model="professionalId"
            :options="professionalOptions"
            :invalid="invalid"
            placeholder="Quem atende"
          />
        </template>
      </FormField>

      <FormField label="Data e hora" :error="errors.scheduled_at" html-for="lead-convert-when">
        <template #default="{ invalid }">
          <DateTimePicker id="lead-convert-when" v-model="scheduledAt" :invalid="invalid" />
        </template>
      </FormField>

      <FormField
        label="Duração (minutos)"
        hint="Vazio usa 60 minutos."
        :error="errors.duration_minutes"
        html-for="lead-convert-duration"
      >
        <template #default="{ invalid }">
          <Input
            id="lead-convert-duration"
            v-model="duration"
            type="number"
            :invalid="invalid"
            placeholder="Opcional"
          />
        </template>
      </FormField>

      <FormField label="Notas" :error="errors.notes" html-for="lead-convert-notes" class="md:col-span-2">
        <template #default="{ invalid }">
          <Textarea id="lead-convert-notes" v-model="notes" :rows="2" :invalid="invalid" />
        </template>
      </FormField>
    </div>
    <template #footer-negative>
      <Button mobile-block variant="ghost" type="button" :disabled="isPending" @click="open = false"
        >Cancelar</Button
      >
    </template>
    <template #footer>
      <Button mobile-block type="button" :loading="isPending" @click="onConfirm">Confirmar</Button>
    </template>
  </AppDialog>
</template>
