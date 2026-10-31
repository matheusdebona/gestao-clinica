import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { markLeadMilestone, type LeadQuickMilestone } from '@/features/leads/api'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'

export function useLeadMilestone() {
  const queryClient = useQueryClient()
  const toast = useToastStore()

  return useMutation({
    mutationFn: (vars: { id: number; milestone: LeadQuickMilestone }) =>
      markLeadMilestone(vars.id, vars.milestone),
    onSuccess: async (_lead, vars) => {
      toast.success(vars.milestone === 'approached' ? 'Marcado como abordado' : 'Marcado: respondeu')
      await queryClient.invalidateQueries({ queryKey: ['leads'] })
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : 'Não foi possível marcar.')
    },
  })
}
