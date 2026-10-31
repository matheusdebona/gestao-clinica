<script setup lang="ts">
import Badge from '@/components/ui/Badge.vue'
import { activityLabel } from '@/features/leads/labels'
import { formatClinicDateTime } from '@/lib/formatters'
import type { LeadActivity } from '@/types/lead'

defineProps<{
  activities: LeadActivity[]
}>()
</script>

<template>
  <ol class="flex flex-col divide-y divide-border-divider">
    <li v-for="activity in activities" :key="activity.id" class="py-3 first:pt-0 last:pb-0">
      <template v-if="activity.type === 'comment'">
        <p class="text-[13px] text-muted">
          <span class="font-medium text-title">{{ activity.user?.name ?? 'Usuário removido' }}</span>
          · {{ formatClinicDateTime(activity.created_at) }}
        </p>
        <p class="mt-1 whitespace-pre-wrap text-[15px] text-body">{{ activity.note }}</p>
      </template>
      <template v-else>
        <div class="flex flex-wrap items-center gap-2">
          <p class="text-[15px] font-medium text-title">{{ activityLabel(activity) }}</p>
          <Badge v-if="activity.to_status" :variant="activity.to_status.color">
            {{ activity.to_status.name }}
          </Badge>
        </div>
        <p v-if="activity.note" class="mt-1 whitespace-pre-wrap text-[15px] text-body">
          <template v-if="activity.type === 'cancelled'">Motivo: </template>{{ activity.note }}
        </p>
        <p class="mt-1 text-[13px] text-muted">
          {{ formatClinicDateTime(activity.created_at) }}
          <template v-if="activity.user"> · {{ activity.user.name }}</template>
        </p>
      </template>
    </li>
  </ol>
</template>
