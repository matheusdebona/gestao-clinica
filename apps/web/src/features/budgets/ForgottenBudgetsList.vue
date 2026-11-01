<script setup lang="ts">
import { useQueryClient } from '@tanstack/vue-query'
import { useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import ListCard from '@/components/ui/ListCard.vue'
import { logBudgetFollowUpWhatsApp } from '@/features/budgets/api'
import {
  forgottenLabel,
  forgottenVariant,
  type ForgottenBudgetRow,
} from '@/features/budgets/forgotten'
import { useWhatsAppLinks } from '@/features/settings/useWhatsAppLinks'
import { formatBRL, formatDateTime } from '@/lib/formatters'
import { openWhatsApp } from '@/lib/whatsapp'
import { useToastStore } from '@/stores/toast'

defineProps<{ items: ForgottenBudgetRow[] }>()

const router = useRouter()
const toast = useToastStore()
const queryClient = useQueryClient()
const { budgetUrl } = useWhatsAppLinks()

function meta(item: ForgottenBudgetRow): string {
  const bits = [`v${item.version} · ${formatBRL(item.effective_amount)}`]
  if (item.sent_at) {
    bits.push(`enviado ${formatDateTime(item.sent_at)}`)
  }
  if (item.follow_up_sent_at) {
    bits.push(`cobrado ${formatDateTime(item.follow_up_sent_at)}`)
  }
  return bits.join(' · ')
}

function followUp(item: ForgottenBudgetRow) {
  const url = budgetUrl(item)
  if (!url) {
    toast.error('WhatsApp do paciente inválido.')
    return
  }
  openWhatsApp(url)
  void logBudgetFollowUpWhatsApp(item.id)
    .then(async () => {
      await queryClient.invalidateQueries({ queryKey: ['budgets'] })
      await queryClient.invalidateQueries({ queryKey: ['leads', 'daily'] })
    })
    .catch(() => undefined)
}
</script>

<template>
  <ul class="flex flex-col divide-y divide-border-divider">
    <li v-for="item in items" :key="item.id" class="flex flex-col gap-2 py-2 first:pt-0 last:pb-1">
      <ListCard
        :title="item.client?.name ?? `Paciente #${item.client_id}`"
        :meta="meta(item)"
        @action="router.push({ name: 'sales-show', params: { id: String(item.sale_id) } })"
      >
        <template v-if="item.forgotten" #status>
          <Badge :variant="forgottenVariant(item.forgotten)">{{
            forgottenLabel(item.forgotten)
          }}</Badge>
        </template>
      </ListCard>
      <PermissionGate permission="budgets.update">
        <div class="flex flex-col gap-2 sm:flex-row">
          <Button
            mobile-block
            :variant="item.forgotten?.needs_follow_up === false ? 'ghost' : 'secondary'"
            :disabled="!item.client?.whatsapp"
            @click="followUp(item)"
          >
            {{
              item.forgotten?.needs_follow_up === false ? 'Cobrar de novo' : 'Cobrar no WhatsApp'
            }}
          </Button>
        </div>
      </PermissionGate>
    </li>
  </ul>
</template>
