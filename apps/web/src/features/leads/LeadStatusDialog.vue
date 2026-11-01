<script setup lang="ts">
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'
import AppDialog from '@/components/ui/AppDialog.vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Select from '@/components/ui/Select.vue'
import Textarea from '@/components/ui/Textarea.vue'
import { changeLeadStatus } from '@/features/leads/api'
import { LEAD_SYSTEM_KEYS } from '@/features/leads/labels'
import { useToastStore } from '@/stores/toast'
import type { Lead, LeadStatus } from '@/types/lead'
import { ApiError } from '@/types/user'

const open = defineModel<boolean>('open', { default: false })

const props = defineProps<{
  lead: Pick<Lead, 'id' | 'name' | 'lead_status_id'> | null
  statuses: LeadStatus[]
}>()

const emit = defineEmits<{
  saved: [lead: Lead]
}>()

const toast = useToastStore()
const queryClient = useQueryClient()
const statusId = ref('')
const note = ref('')
const error = ref('')

watch(open, (isOpen) => {
  if (isOpen && props.lead) {
    statusId.value = String(props.lead.lead_status_id)
    note.value = ''
    error.value = ''
  }
})

// System statuses ("Virou paciente", "Cancelado") are only set by their flows.
const options = computed(() =>
  props.statuses
    .filter((status) => status.is_active && !LEAD_SYSTEM_KEYS.includes(status.system_key ?? ''))
    .map((status) => ({ value: String(status.id), label: status.name })),
)

const { mutate, isPending } = useMutation({
  mutationFn: () =>
    changeLeadStatus(props.lead!.id, {
      lead_status_id: Number(statusId.value),
      note: note.value.trim() || null,
    }),
  onSuccess: async (lead) => {
    toast.success('Status atualizado')
    open.value = false
    emit('saved', lead)
    await queryClient.invalidateQueries({ queryKey: ['leads'] })
  },
  onError: (err) => {
    if (err instanceof ApiError && err.status === 422) {
      error.value = err.first('lead_status_id') || err.first('note') || err.message
      return
    }
    toast.error(err instanceof ApiError ? err.message : 'Não foi possível atualizar.')
  },
})
</script>

<template>
  <AppDialog
      size="md"
    stack-footer
    v-model:open="open"
    title="Mudar status"
    :description="lead ? lead.name : ''"
  >
    <div class="flex flex-col gap-4">
      <FormField label="Status" :error="error" html-for="lead-quick-status">
        <template #default="{ invalid }">
          <Select id="lead-quick-status" v-model="statusId" :options="options" :invalid="invalid" />
        </template>
      </FormField>
      <FormField
        label="Observação (opcional)" hint="Ex.: restrição, por que avançou ou não."
        html-for="lead-quick-note"
      >
        <Textarea id="lead-quick-note" v-model="note" :rows="3" />
      </FormField>
    </div>
    <template #footer-negative>
      <Button mobile-block variant="ghost" type="button" @click="open = false">Cancelar</Button>
    </template>
    <template #footer>
      <Button
        mobile-block
        type="button"
        :loading="isPending"
        :disabled="!statusId"
        @click="mutate()"
      >
        Salvar
      </Button>
    </template>
  </AppDialog>
</template>
