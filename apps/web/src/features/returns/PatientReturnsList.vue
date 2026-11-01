<script setup lang="ts">
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import ActionBar from '@/components/ui/ActionBar.vue'
import Button from '@/components/ui/Button.vue'
import ListCard from '@/components/ui/ListCard.vue'
import {
  logPatientReturnWhatsApp,
  markPatientReturnDone,
  returnDueLabel,
  returnDueVariant,
  type PatientReturn,
} from '@/features/returns/api'
import { useWhatsAppLinks } from '@/features/settings/useWhatsAppLinks'
import { formatIsoDate } from '@/lib/formatters'
import { openWhatsApp } from '@/lib/whatsapp'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'

defineProps<{ items: PatientReturn[]; hideActions?: boolean }>()

const router = useRouter()
const toast = useToastStore()
const queryClient = useQueryClient()
const { returnUrl } = useWhatsAppLinks()

const doneMutation = useMutation({
  mutationFn: (id: number) => markPatientReturnDone(id),
  onSuccess: async () => {
    toast.success('Retorno marcado como feito')
    await queryClient.invalidateQueries({ queryKey: ['patient-returns'] })
    await queryClient.invalidateQueries({ queryKey: ['leads', 'daily'] })
  },
  onError: (error) => {
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível marcar o retorno.')
  },
})

function meta(item: PatientReturn): string {
  const bits = [item.label, `previsto para ${formatIsoDate(item.due_on)}`]
  if (item.whatsapp_sent_at) {
    bits.push('WhatsApp aberto')
  }
  return bits.join(' · ')
}

function callOnWhatsApp(item: PatientReturn) {
  const url = returnUrl(item)
  if (!url) {
    toast.error('WhatsApp do paciente inválido.')
    return
  }
  openWhatsApp(url)
  void logPatientReturnWhatsApp(item.id)
    .then(() => queryClient.invalidateQueries({ queryKey: ['patient-returns'] }))
    .catch(() => undefined)
}

function openClient(item: PatientReturn) {
  void router.push({ name: 'clients-show', params: { id: String(item.client_id) } })
}
</script>

<template>
  <ul class="flex flex-col divide-y divide-border-divider">
    <li v-for="item in items" :key="item.id" class="flex flex-col gap-2 py-2 first:pt-0 last:pb-1">
      <ListCard
        :title="item.client?.name ?? `Paciente #${item.client_id}`"
        :meta="meta(item)"
        :badge="item.status === 'pending' ? returnDueLabel(item.days_until_due) : 'Feito'"
        :badge-variant="
          item.status === 'pending' ? returnDueVariant(item.days_until_due) : 'success'
        "
        @action="openClient(item)"
      />
      <PermissionGate
        v-if="!hideActions && item.status === 'pending'"
        permission="clients.update"
      >
        <ActionBar>
          <Button
            mobile-block
            variant="secondary"
            :disabled="!item.client?.whatsapp"
            @click="callOnWhatsApp(item)"
          >
            Chamar no WhatsApp
          </Button>
          <Button
            mobile-block
            variant="ghost"
            :loading="doneMutation.isPending.value && doneMutation.variables.value === item.id"
            @click="doneMutation.mutate(item.id)"
          >
            Marcar como feito
          </Button>
        </ActionBar>
      </PermissionGate>
    </li>
  </ul>
</template>
