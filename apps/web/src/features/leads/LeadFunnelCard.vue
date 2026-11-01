<script setup lang="ts">
import InfoTip from '@/components/ui/InfoTip.vue'
import { computed } from 'vue'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { MILESTONE_LABELS, nextQuickMilestone, quickMilestoneLabel } from '@/features/leads/milestones'
import { useLeadMilestone } from '@/features/leads/useLeadMilestone'
import { formatClinicDateTime } from '@/lib/formatters'
import type { Lead } from '@/types/lead'

const props = defineProps<{ lead: Lead }>()

const steps = computed(() => [
  { key: 'approached', label: MILESTONE_LABELS.approached, at: props.lead.approached_at, by: props.lead.approached_by },
  { key: 'replied', label: MILESTONE_LABELS.replied, at: props.lead.replied_at, by: props.lead.replied_by },
  { key: 'booked', label: MILESTONE_LABELS.booked, at: props.lead.booked_at, by: props.lead.booked_by },
])

const next = computed(() => nextQuickMilestone(props.lead))
const { mutate, isPending } = useLeadMilestone()
</script>

<template>
  <SurfaceCard>
    <div class="mb-3 flex items-center gap-1.5"><h2>Funil</h2><InfoTip text="O primeiro WhatsApp aberto já marca como abordado. &quot;Agendou&quot; é marcado ao agendar a avaliação." label="Sobre: Funil" /></div>
    <ol class="flex flex-col gap-3">
      <li v-for="step in steps" :key="step.key" class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="text-[15px] text-title">{{ step.label }}</p>
          <p class="mt-0.5 text-[13px] text-muted">
            <template v-if="step.at">
              {{ formatClinicDateTime(step.at) }}<template v-if="step.by"> · {{ step.by.name }}</template>
            </template>
            <template v-else>Ainda não</template>
          </p>
        </div>
        <Badge :variant="step.at ? 'success' : 'muted'">{{ step.at ? 'Feito' : 'Pendente' }}</Badge>
      </li>
    </ol>
    <PermissionGate v-if="next" permission="leads.manage">
      <div class="mt-4 flex sm:justify-end">
        <Button mobile-block :loading="isPending" @click="mutate({ id: lead.id, milestone: next })">
          {{ quickMilestoneLabel(next) }}
        </Button>
      </div>
    </PermissionGate>
  </SurfaceCard>
</template>
