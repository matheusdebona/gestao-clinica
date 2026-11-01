<script setup lang="ts">
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { ref } from 'vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Textarea from '@/components/ui/Textarea.vue'
import { updateLeadComment } from '@/features/leads/api'
import { activityLabel } from '@/features/leads/labels'
import { formatClinicDateTime } from '@/lib/formatters'
import { useToastStore } from '@/stores/toast'
import type { LeadActivity } from '@/types/lead'
import { ApiError } from '@/types/user'

const props = defineProps<{
  activities: LeadActivity[]
  /** When set, the latest comment can be edited inline (if the API allows it: author or admin). */
  leadId?: number
}>()

const toast = useToastStore()
const queryClient = useQueryClient()

const editingId = ref<number | null>(null)
const draft = ref('')
const draftError = ref('')

function canEdit(activity: LeadActivity): boolean {
  return props.leadId !== undefined && activity.type === 'comment' && activity.can_edit === true
}

function startEdit(activity: LeadActivity) {
  editingId.value = activity.id
  draft.value = activity.note ?? ''
  draftError.value = ''
}

function cancelEdit() {
  editingId.value = null
  draft.value = ''
  draftError.value = ''
}

const editMutation = useMutation({
  mutationFn: (commentId: number) =>
    updateLeadComment(props.leadId as number, commentId, { body: draft.value.trim() }),
  onSuccess: async () => {
    toast.success('Comentário atualizado')
    cancelEdit()
    await queryClient.invalidateQueries({ queryKey: ['leads'] })
    await queryClient.invalidateQueries({ queryKey: ['clients'] })
  },
  onError: async (error) => {
    if (error instanceof ApiError && error.status === 422) {
      draftError.value = error.first('body') || error.message
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível salvar o comentário.')
    if (error instanceof ApiError && error.status === 403) {
      cancelEdit()
      await queryClient.invalidateQueries({ queryKey: ['leads'] })
    }
  },
})

function saveEdit(activity: LeadActivity) {
  if (!draft.value.trim()) {
    draftError.value = 'Escreva o comentário.'
    return
  }
  if (draft.value.trim() === (activity.note ?? '').trim()) {
    cancelEdit()
    return
  }
  editMutation.mutate(activity.id)
}
</script>

<template>
  <ol class="flex flex-col divide-y divide-border-divider">
    <li v-for="activity in activities" :key="activity.id" class="py-3 first:pt-0 last:pb-0">
      <template v-if="activity.type === 'comment'">
        <div class="flex items-start justify-between gap-2">
          <p class="text-[13px] text-muted">
            <span class="font-medium text-title">{{ activity.user?.name ?? 'Usuário removido' }}</span>
            · {{ formatClinicDateTime(activity.created_at) }}
            <span v-if="activity.edited_at" :title="`Editado em ${formatClinicDateTime(activity.edited_at)}`">
              · (editado {{ formatClinicDateTime(activity.edited_at) }})
            </span>
          </p>
          <Button
            v-if="canEdit(activity) && editingId !== activity.id"
            variant="ghost"
            class="-my-2 shrink-0"
            @click="startEdit(activity)"
          >
            Editar
          </Button>
        </div>
        <form
          v-if="editingId === activity.id"
          class="mt-2 flex flex-col gap-2"
          @submit.prevent="saveEdit(activity)"
        >
          <FormField
            label="Editar comentário"
            :error="draftError"
            :html-for="`lead-comment-edit-${activity.id}`"
          >
            <template #default="{ invalid }">
              <Textarea
                :id="`lead-comment-edit-${activity.id}`"
                v-model="draft"
                :rows="3"
                :invalid="invalid"
              />
            </template>
          </FormField>
          <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button
              mobile-block
              variant="ghost"
              :disabled="editMutation.isPending.value"
              @click="cancelEdit"
            >
              Cancelar
            </Button>
            <Button
              mobile-block
              type="submit"
              variant="secondary"
              :loading="editMutation.isPending.value"
              :disabled="!draft.trim()"
            >
              Salvar
            </Button>
          </div>
        </form>
        <p v-else class="mt-1 whitespace-pre-wrap text-[15px] text-body">{{ activity.note }}</p>
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
